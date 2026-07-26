"""
Chat Service — xử lý logic chatbot tư vấn.
"""

from __future__ import annotations

from models.schemas import ChatContext, ChatRequest, TokenUsage
from services.provider import call_provider
from utils.context import build_context_summary


# =============================================================================
# System prompts theo role
# =============================================================================

SYSTEM_PROMPTS = {
    "default": (
        "Bạn là trợ lý AI của Eript LMS — hệ thống quản lý học tập trực tuyến. "
        "Nhiệm vụ là tư vấn khóa học, giải thích cách dùng hệ thống, gợi ý lộ trình học và trả lời ngắn gọn bằng tiếng Việt. "
        "Ưu tiên dùng dữ liệu thật trong phần context. Không bịa khóa học không có trong context. "
        "Nếu người dùng hỏi chung chung, hãy hỏi lại ngắn gọn hoặc gợi ý 3-4 hướng cụ thể. "
        "Nếu có thể, hãy đề xuất tối đa 4 khóa học phù hợp và nêu ngắn lý do chọn."
    ),
    "admin": (
        "Bạn là trợ lý AI của Eript LMS, đang hỗ trợ quản trị viên. "
        "Bạn có thể tư vấn về quản lý khóa học, phân tích dữ liệu, và gợi ý cải thiện hệ thống. "
        "Trả lời chuyên nghiệp, sử dụng dữ liệu thực từ context. "
        "Khi được hỏi về khóa học, hãy cung cấp thông tin chi tiết bao gồm số lượng đăng ký, đánh giá."
    ),
    "instructor": (
        "Bạn là trợ lý AI của Eript LMS, đang hỗ trợ giảng viên. "
        "Bạn giúp tạo nội dung khóa học, câu hỏi, cải thiện chất lượng giảng dạy. "
        "Trả lời bằng tiếng Việt, tập trung vào khóa học và nội dung đang xem."
    ),
    "student": (
        "Bạn là trợ lý AI của Eript LMS, đang hỗ trợ sinh viên. "
        "Nhiệm vụ là tư vấn khóa học phù hợp, giải đáp thắc mắc về bài học, và gợi ý lộ trình học tập. "
        "Trả lời thân thiện, dễ hiểu bằng tiếng Việt. "
        "Ưu tiên gợi ý từ các khóa học có trong hệ thống."
    ),
}


def get_system_prompt(role: str | None = None) -> str:
    """Lấy system prompt phù hợp theo role."""
    return SYSTEM_PROMPTS.get(role or "default", SYSTEM_PROMPTS["default"])


def build_ai_messages(
    payload: ChatRequest,
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

    # User message hiện tại + context
    user_content = (
        f"Câu hỏi người dùng: {payload.message.strip()}\n\n"
        f"Context hệ thống:\n{context_summary or 'Không có dữ liệu ngữ cảnh.'}"
    )
    messages.append({"role": "user", "content": user_content})

    return messages


async def chat(
    payload: ChatRequest,
    role: str | None = None,
) -> tuple[str | None, TokenUsage]:
    """
    Xử lý chat request — build messages và gọi AI provider.

    Returns:
        (reply_text, token_usage)
    """
    messages = build_ai_messages(payload, role=role)
    return await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
    )
