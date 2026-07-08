"""
Chat Service — xử lý logic chatbot tư vấn.
"""

from __future__ import annotations

from models.schemas import ChatContext, ChatRequest, TokenUsage
from services.provider import call_provider
from utils.context import build_context_summary
from services.rag import retriever


# =============================================================================
# System prompts theo role
# =============================================================================

SYSTEM_PROMPTS = {
    "default": (
        "Bạn là trợ lý AI của Sylva LMS — hệ thống quản lý học tập trực tuyến. "
        "Nhiệm vụ là tư vấn khóa học, giải thích cách dùng hệ thống, gợi ý lộ trình học và trả lời ngắn gọn bằng tiếng Việt. "
        "Ưu tiên dùng dữ liệu thật trong phần context. Không bịa khóa học không có trong context. "
        "Nếu người dùng hỏi chung chung, hãy hỏi lại ngắn gọn hoặc gợi ý 3-4 hướng cụ thể. "
        "Nếu có thể, hãy đề xuất tối đa 4 khóa học phù hợp và nêu ngắn lý do chọn. "
        "Khi trả lời về kiến thức học thuật, nếu được cung cấp TÀI LIỆU THAM KHẢO, hãy ưu tiên trả lời dựa trên tài liệu đó "
        "và chỉ ra số nguồn tham khảo tương ứng (ví dụ: [1], [2])."
    ),
    "admin": (
        "Bạn là trợ lý AI của Sylva LMS, đang hỗ trợ quản trị viên. "
        "Bạn có thể tư vấn về quản lý khóa học, phân tích dữ liệu, và gợi ý cải thiện hệ thống. "
        "Trả lời chuyên nghiệp, sử dụng dữ liệu thực từ context. "
        "Khi được hỏi về khóa học, hãy cung cấp thông tin chi tiết bao gồm số lượng đăng ký, đánh giá."
    ),
    "instructor": (
        "Bạn là trợ lý AI của Sylva LMS, đang hỗ trợ giảng viên. "
        "Bạn giúp tạo nội dung khóa học, câu hỏi, cải thiện chất lượng giảng dạy. "
        "Trả lời bằng tiếng Việt, tập trung vào khóa học và nội dung đang xem."
    ),
    "student": (
        "Bạn là trợ lý AI của Sylva LMS, đang hỗ trợ sinh viên. "
        "Nhiệm vụ là tư vấn khóa học phù hợp, giải đáp thắc mắc về bài học, và gợi ý lộ trình học tập. "
        "Trả lời thân thiện, dễ hiểu bằng tiếng Việt. "
        "Ưu tiên gợi ý từ các khóa học có trong hệ thống. "
        "Khi được cung cấp TÀI LIỆU THAM KHẢO học thuật bên dưới câu hỏi, hãy ưu tiên trả lời chính xác dựa theo tài liệu đó "
        "và ghi rõ nguồn trích dẫn từ tài liệu (ví dụ: [1], [2])."
    ),
}


def get_system_prompt(role: str | None = None) -> str:
    """Lấy system prompt phù hợp theo role."""
    return SYSTEM_PROMPTS.get(role or "default", SYSTEM_PROMPTS["default"])


def build_ai_messages(
    payload: ChatRequest,
    rag_context: str = "",
    role: str | None = None,
) -> list[dict[str, str]]:
    """
    Xây dựng danh sách messages cho AI provider.

    Returns:
        [{"role": "system"|"user"|"assistant", "content": "..."}]
    """
    context = payload.context or ChatContext()
    context_summary = build_context_summary(context)
    system_prompt = get_system_prompt(role)

    messages: list[dict[str, str]] = [
        {"role": "system", "content": system_prompt},
    ]

    # Thêm conversation history nếu có
    for msg in payload.history:
        if msg.get("role") in ("user", "assistant") and msg.get("content"):
            messages.append({
                "role": msg["role"],
                "content": msg["content"],
            })

    # User message hiện tại + context + RAG
    user_content = (
        f"Câu hỏi người dùng: {payload.message.strip()}\n\n"
    )
    if rag_context:
        user_content += f"{rag_context}\n\n"
        
    user_content += f"Context hệ thống:\n{context_summary or 'Không có dữ liệu ngữ cảnh.'}"
    
    messages.append({"role": "user", "content": user_content})

    return messages


async def chat(
    payload: ChatRequest,
    role: str | None = None,
) -> tuple[str | None, list[dict], bool, TokenUsage]:
    """
    Xử lý chat request — truy xuất RAG context, build messages và gọi AI provider.

    Returns:
        (reply_text, sources, has_rag_context, token_usage)
    """
    # 1. Truy xuất tài liệu từ RAG (nếu có môn học hoặc khóa học)
    chunks = []
    rag_context = ""
    has_rag_context = False

    try:
        # Lấy tên môn học từ context hoặc khóa học hiện tại nếu có
        subject_name = None
        if payload.context and payload.context.current_course:
            subject_name = payload.context.current_course.title

        chunks = retriever.retrieve_for_chat(
            question=payload.message,
            course_id=payload.course_id,
            subject_name=subject_name,
            top_k=4
        )
        if chunks:
            rag_context = retriever.format_context_for_prompt(chunks)
            has_rag_context = True
    except Exception as e:
        import logging
        logging.getLogger(__name__).error(f"Retrieve RAG context failed: {e}")

    # 2. Build messages
    messages = build_ai_messages(payload, rag_context=rag_context, role=role)

    # 3. Call AI provider
    reply, tokens = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
    )

    # Format sources trả về
    sources_out = []
    for c in chunks:
        sources_out.append({
            "source_file": c["metadata"].get("source_file", "Tài liệu"),
            "subject_name": c["metadata"].get("subject_name", ""),
            "relevance_score": c.get("relevance_score", 0.0),
            "content_preview": c["content"][:300]
        })

    return reply, sources_out, has_rag_context, tokens

