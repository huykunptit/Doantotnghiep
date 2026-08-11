"""
Chat Service — xử lý logic chatbot tư vấn + RAG giáo trình.
"""

from __future__ import annotations

import re
from typing import AsyncGenerator

from models.schemas import EMPTY_TOKENS, ChatContext, ChatRequest, RagSource, TokenUsage
from services.provider import call_provider, stream_provider
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
# Quy tắc định dạng chung (chat bubble hiển thị plain text)
# =============================================================================

FORMAT_RULES = (
    "Định dạng trả lời (BẮT BUỘC):\n"
    "- Chỉ dùng văn bản thuần tiếng Việt. KHÔNG dùng markdown: không **in đậm**, không # tiêu đề, không ---\n"
    "- Không dùng emoji hay icon trang trí (ví dụ rocket, bóng đèn, mặt cười).\n"
    "- Viết ngắn gọn, rõ ý. Liệt kê dùng dấu gạch đầu dòng '-' nếu cần.\n"
    "- Khi gợi ý khóa trên Eript: CHỈ lấy tên khóa có trong context và liên quan TRỰC TIẾP câu hỏi.\n"
    "- Không gắn môn đại cương lệch ngành (Tiếng Anh, Tư tưởng, Pháp luật, Xác suất, Giải tích, Toán rời rạc...) "
    "vào lộ trình lập trình trừ khi người dùng hỏi đúng môn đó.\n"
    "- Nếu context thiếu khóa đúng stack (ví dụ chưa có Java/Spring): nói thẳng là hiện chưa có khóa chuyên sâu đó, "
    "có thể gợi ý khóa CNTT/CSDL/lập trình gần nhất nếu có trong context — không bịa khóa, không nhồi khóa cho đủ số."
)


# =============================================================================
# System prompts theo role
# =============================================================================

SYSTEM_PROMPTS = {
    "default": (
        "Bạn là trợ lý AI của Eript LMS — hệ thống quản lý học tập trực tuyến. "
        "Nhiệm vụ là tư vấn khóa học, giải thích cách dùng hệ thống, gợi ý lộ trình học và trả lời ngắn gọn bằng tiếng Việt. "
        "Ưu tiên dùng dữ liệu thật trong phần context. Không bịa khóa học không có trong context. "
        "Nếu người dùng hỏi chung chung, hãy hỏi lại ngắn gọn hoặc gợi ý 3-4 hướng cụ thể. "
        "Nếu có thể, hãy đề xuất tối đa 3 khóa học thật sự phù hợp và nêu ngắn lý do chọn.\n"
        f"{FORMAT_RULES}"
    ),
    "guest": (
        "Bạn là trợ lý AI công khai của Eript LMS, đóng vai tư vấn tuyển sinh chuyên nghiệp, thân thiện. "
        "Trả lời bằng tiếng Việt tự nhiên, ưu tiên 4–8 câu/đoạn ngắn, rõ, giàu giá trị. "
        "Khi khách hỏi giới thiệu web/website/hệ thống/nền tảng/Eript: pitch Eript LMS, "
        "nêu 3 lĩnh vực đào tạo (CNTT, QTKD, ĐTVT), kể tên vài khóa nổi bật từ context, "
        "kể vài lộ trình nghề từ career_paths trong context, rồi CTA mời đăng ký. "
        "Được phép: hướng dẫn đăng ký/đăng nhập, giới thiệu khóa công khai, tư vấn nghề định hướng, "
        "gợi ý tối đa 2–3 khóa thật sự liên quan từ context. "
        "Không được: giải thích bài quá sâu, làm bài/quiz hộ, phân tích GPA cá nhân, đánh giá CV sâu. "
        "Khi câu hỏi vượt mức công khai, từ chối mềm rồi mời đăng nhập. Không bịa khóa/lộ trình.\n"
        f"{FORMAT_RULES}"
    ),
    "admin": (
        "Bạn là trợ lý AI của Eript LMS, đang hỗ trợ quản trị viên. "
        "Tư vấn quản lý khóa học, phân tích dữ liệu, gợi ý cải thiện hệ thống. "
        "Trả lời chuyên nghiệp, dùng dữ liệu thực từ context.\n"
        f"{FORMAT_RULES}"
    ),
    "instructor": (
        "Bạn là trợ lý AI của Eript LMS, đang hỗ trợ giảng viên. "
        "Giúp tạo nội dung, câu hỏi, cải thiện chất lượng giảng dạy. "
        "Trả lời bằng tiếng Việt, tập trung vào khóa học và nội dung đang xem.\n"
        f"{FORMAT_RULES}"
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
        "Nếu tài liệu không đủ, hãy nói chưa đủ căn cứ và gợi ý hỏi cụ thể hơn. "
        "Ưu tiên gợi ý từ các khóa học có trong hệ thống và khớp chủ đề người hỏi "
        "(ví dụ hỏi Fullstack Java thì ưu tiên khóa lập trình/CNTT/CSDL; không kéo Tiếng Anh hay Pháp luật vào trừ khi được hỏi).\n"
        f"{FORMAT_RULES}"
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


def sanitize_reply(text: str | None) -> str | None:
    """Gỡ markdown/emoji để hiển thị đẹp trong chat bubble plain text."""
    if not text:
        return text

    cleaned = text
    # **bold** / __bold__ / *italic*
    cleaned = re.sub(r"\*\*(.+?)\*\*", r"\1", cleaned, flags=re.DOTALL)
    cleaned = re.sub(r"__(.+?)__", r"\1", cleaned, flags=re.DOTALL)
    cleaned = re.sub(r"(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)", r"\1", cleaned, flags=re.DOTALL)
    # headings ### Title
    cleaned = re.sub(r"^#{1,6}\s*", "", cleaned, flags=re.MULTILINE)
    # horizontal rules
    cleaned = re.sub(r"^[-*_]{3,}\s*$", "", cleaned, flags=re.MULTILINE)
    # list markers "* item" → "- item" (giữ gạch đầu dòng đơn giản)
    cleaned = re.sub(r"^\s*[\*\u2022]\s+", "- ", cleaned, flags=re.MULTILINE)
    # emoji / symbol trang trí phổ biến
    cleaned = re.sub(
        r"[\U0001F300-\U0001FAFF\U00002700-\U000027BF\U0001F1E0-\U0001F1FF\U00002600-\U000026FF]+",
        "",
        cleaned,
    )
    # dọn khoảng trắng thừa sau khi xóa emoji
    cleaned = re.sub(r"[ \t]{2,}", " ", cleaned)
    cleaned = re.sub(r"[ \t]+\n", "\n", cleaned)
    cleaned = re.sub(r"\n{3,}", "\n\n", cleaned)
    return cleaned.strip()


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
    parts.append(
        "Nhắc lại: trả lời plain text, không markdown, không emoji, "
        "chỉ gợi ý khóa liên quan trực tiếp từ context."
    )

    messages.append({"role": "user", "content": "\n\n".join(parts)})
    return messages


def _build_rag(payload: ChatRequest, role: str | None) -> tuple[bool, list[RagSource], str]:
    """Truy hồi RAG (nếu bật + sẵn sàng). Dùng chung cho chat() và chat_stream()."""
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

    return rag_used, sources, rag_block


async def chat(
    payload: ChatRequest,
    role: str | None = None,
) -> tuple[str | None, TokenUsage, bool, list[RagSource]]:
    """
    Xử lý chat request — build messages (có RAG nếu sẵn sàng) và gọi AI provider.

    Returns:
        (reply_text, token_usage, rag_used, sources)
    """
    rag_used, sources, rag_block = _build_rag(payload, role)

    messages = build_ai_messages(payload, role=role, rag_block=rag_block)
    reply, tokens = await call_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
    )
    return sanitize_reply(reply), tokens, rag_used, sources


async def chat_stream(
    payload: ChatRequest,
    role: str | None = None,
) -> AsyncGenerator[dict, None]:
    """
    Bản streaming của chat() — build messages giống hệt, nhưng sinh token theo
    thời gian thực thay vì đợi trả lời đầy đủ.

    Yields:
        {"rag": {"used": bool, "sources": [dict, ...]}}  — 1 lần, trước khi có token đầu tiên
        {"delta": "..."}  — từng đoạn văn bản mới (đã sanitize markdown/emoji)
        {"usage": TokenUsage}  — 1 lần, ở cuối
    """
    rag_used, sources, rag_block = _build_rag(payload, role)
    yield {"rag": {"used": rag_used, "sources": [s.model_dump() for s in sources]}}

    messages = build_ai_messages(payload, role=role, rag_block=rag_block)

    full_so_far = ""
    sanitized_so_far = ""
    usage: TokenUsage = {**EMPTY_TOKENS}

    async for item in stream_provider(
        provider=payload.provider or "chatgpt",
        api_key=payload.api_key or "",
        messages=messages,
        model=payload.model,
    ):
        if "delta" in item:
            full_so_far += item["delta"]
            # sanitize_reply() bóc markdown/emoji trên toàn bộ text tích lũy (không thể áp
            # regex trên từng token rời rạc vì marker như "**" có thể bị cắt giữa 2 chunk),
            # rồi chỉ gửi phần hậu tố mới cho client để giữ hiệu ứng gõ dần.
            sanitized_full = sanitize_reply(full_so_far) or ""
            if len(sanitized_full) > len(sanitized_so_far):
                suffix = sanitized_full[len(sanitized_so_far):]
                sanitized_so_far = sanitized_full
                yield {"delta": suffix}
        elif "usage" in item:
            usage = item["usage"]

    yield {"usage": usage}
