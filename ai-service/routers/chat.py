"""
Chat Router — endpoint chatbot tư vấn.
"""

from __future__ import annotations

from fastapi import APIRouter, HTTPException

from models.schemas import ChatRequest, ChatResponseWithSources
from services import chat_service

router = APIRouter(tags=["Chat"])


@router.post("/chat", response_model=ChatResponseWithSources)
async def chat(payload: ChatRequest) -> ChatResponseWithSources:
    """
    Gửi tin nhắn cho chatbot AI.

    Hỗ trợ:
    - 3 providers: chatgpt, gemini, openrouter, claude
    - Context: khóa học, danh mục, khóa đang xem
    - History: lịch sử hội thoại (gửi kèm từ client)
    - RAG: Tự động truy xuất tài liệu 160+ môn học PTIT và đính kèm nguồn
    """
    if not payload.api_key:
        return ChatResponseWithSources(
            reply=(
                "Xin chào! Tôi là trợ lý AI của ERIPT LMS.\n\n"
                "⚠️ Hiện tại hệ thống chưa được cấu hình API key cho dịch vụ AI. "
                "Vui lòng liên hệ **quản trị viên** để kích hoạt đầy đủ tính năng trợ lý AI.\n\n"
                "Trong lúc chờ đợi, bạn có thể:\n"
                "• Tìm kiếm khóa học trực tiếp trên trang chủ\n"
                "• Xem danh sách khóa học đã đăng ký\n"
                "• Liên hệ hỗ trợ qua email"
            ),
            sources=[],
            has_rag_context=False,
            tokens_used={"prompt": 0, "completion": 0, "total": 0},
        )

    provider = (payload.provider or "chatgpt").strip().lower()
    if provider not in {"chatgpt", "gemini", "openrouter", "claude"}:
        raise HTTPException(
            status_code=400,
            detail=f"Provider không hỗ trợ: {provider}",
        )

    reply, sources, has_rag, tokens = await chat_service.chat(payload, role=payload.role)
    if not reply:
        raise HTTPException(
            status_code=502,
            detail="Provider AI không trả về nội dung.",
        )

    return ChatResponseWithSources(
        reply=reply,
        sources=sources,
        has_rag_context=has_rag,
        tokens_used=tokens
    )
