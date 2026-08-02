"""
Chat Service — xử lý logic chatbot tư vấn + RAG giáo trình.
"""

from __future__ import annotations

from models.schemas import AIResponse, ChatContext, ChatRequest, RagSource, TokenUsage
from services.provider import call_provider
from utils.context import build_context_summary

try:
    from rag.retrieve import format_rag_context, retrieve_chunks
    from rag.store import is_rag_ready
except Exception:  # pragma: no cover - rag deps chưa cài
    def format_rag_context(*_a, **_k):  # type: ignore
        return ""

    def retrieve_chunks(*_a, **_k):  # type: ignore
        return []

    def is_rag_ready() -> bool:  # type: ignore
        return False


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
    "guest": (
        "Bạn là trợ lý AI công khai của Eript LMS, đóng vai tư vấn tuyển sinh/seller xuất sắc: "
        "nói mượt, thân thiện, chuyên nghiệp, tạo cảm giác đáng tin và khơi gợi mong muốn đăng ký tài khoản. "
        "Trả lời bằng tiếng Việt tự nhiên, ưu tiên 4–8 câu/đoạn ngắn, rõ, giàu giá trị. "
        "Khi khách hỏi giới thiệu web/website/hệ thống/nền tảng/Eript: PHẢI pitch Eript LMS (không nói công dụng chatbot), "
        "nêu 3 lĩnh vực đào tạo (CNTT, QTKD, ĐTVT), kể tên vài khóa nổi bật từ context, "
        "kể vài lộ trình nghề theo từng ngành từ career_paths trong context, rồi CTA mời đăng ký hoặc hỏi ngành muốn đi. "
        "Bạn phải tận dụng dữ liệu công khai trong context để giới thiệu khóa học, danh mục, lộ trình và lợi ích khi học. "
        "Được phép: hướng dẫn đăng ký/đăng nhập, giới thiệu khóa học công khai, tư vấn nghề ở mức định hướng, "
        "gợi ý 2–3 khóa phù hợp từ context và nêu lợi ích thực tế. "
        "Không được: giải thích bài học quá sâu, làm bài/quiz hộ, phân tích điểm/GPA cá nhân, đánh giá CV sâu, "
        "lộ trình cá nhân hóa theo hồ sơ hoặc dữ liệu nội bộ sau đăng nhập. "
        "Khi câu hỏi vượt mức công khai, từ chối mềm mại rồi mời đăng nhập. "
        "Mỗi câu trả lời nên có CTA mềm. Không bịa khóa/lộ trình không có trong context."
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
        "Nhiệm vụ: (1) tư vấn khóa học/lộ trình trong catalog, (2) giải đáp kiến thức môn học dựa trên "
        "TAI_LIEU_GIAO_TRINH_LIEN_QUAN nếu có, (3) hướng dẫn dùng hệ thống. "
        "Trả lời thân thiện, dễ hiểu bằng tiếng Việt. "
        "Khi có đoạn giáo trình retrieved: ưu tiên bám sát tài liệu, trích ý chính, "
        "và nêu tên nguồn PDF / môn ở cuối nếu đã dùng. "
        "Không bịa công thức/định nghĩa ngoài tài liệu. "
        "Không lấy kiến thức từ giáo trình môn khác ngoài các đoạn đã cung cấp. "
        "Nếu tài liệu không đủ, hãy nói chưa đủ căn cứ và gợi ý hỏi cụ thể hơn."
    ),
}


def get_system_prompt(role: str | None = None) -> str:
    """Lấy system prompt phù hợp theo role."""
    return SYSTEM_PROMPTS.get(role or "default", SYSTEM_PROMPTS["default"])


def _should_use_rag(payload: ChatRequest, role: str | None) -> bool:
    if payload.use_rag is False:
        return False
    if payload.use_rag is True:
        return True
    # Mặc định: bật RAG cho student (và default)
    return (role or "student") in {"student", "default", "instructor"}


def _rag_scope_and_subject(payload: ChatRequest) -> tuple[str, str | None]:
    """
    - Có course_id / current_course / rag_scope=course → chỉ giáo trình môn đó.
    - Còn lại → global (mọi giáo trình; chọn 1 môn theo điểm / hòa thì random).
    """
    scope_raw = (payload.rag_scope or "").strip().lower()
    ctx = payload.context
    in_course = bool(
        payload.course_id
        or (ctx and ctx.current_course and ctx.current_course.id)
        or scope_raw == "course"
    )
    if not in_course:
        return "global", None

    hint = (payload.subject_hint or "").strip() or None
    if not hint and ctx and ctx.current_course and ctx.current_course.title:
        hint = ctx.current_course.title.strip() or None
    return "course", hint


def build_ai_messages(
    payload: ChatRequest,
    role: str | None = None,
    rag_block: str = "",
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

    parts = [
        f"Câu hỏi người dùng: {payload.message.strip()}",
        f"Context hệ thống:\n{context_summary or 'Không có dữ liệu ngữ cảnh.'}",
    ]
    if rag_block:
        parts.append(rag_block)

    messages.append({"role": "user", "content": "\n\n".join(parts)})
    return messages


async def chat(
    payload: ChatRequest,
    role: str | None = None,
) -> tuple[str | None, TokenUsage, bool, list[RagSource]]:
    """
    Xử lý chat request — build messages (có RAG nếu sẵn sàng) và gọi AI provider.

    Returns:
        (reply_text, token_usage, rag_used, sources)
    """
    rag_used = False
    sources: list[RagSource] = []
    rag_block = ""

    if _should_use_rag(payload, role) and is_rag_ready():
        scope, subject_hint = _rag_scope_and_subject(payload)
        chunks = retrieve_chunks(
            payload.message,
            top_k=payload.rag_top_k or 5,
            subject_hint=subject_hint,
            scope=scope,  # type: ignore[arg-type]
        )
        if chunks:
            rag_used = True
            rag_block = format_rag_context(chunks, scope=scope)  # type: ignore[arg-type]
            # unique sources
            seen: set[str] = set()
            for ch in chunks:
                key = ch.get("source") or ""
                if key and key not in seen:
                    seen.add(key)
                    sources.append(RagSource(
                        source=key,
                        subject=ch.get("subject") or None,
                        score=ch.get("score"),
                    ))

    messages = build_ai_messages(payload, role=role, rag_block=rag_block)
    reply, tokens = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
    )
    return reply, tokens, rag_used, sources
