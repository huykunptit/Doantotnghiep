"""
Chat Router — endpoint chatbot tư vấn (+ RAG giáo trình cho sinh viên).
"""

from __future__ import annotations

import json

from fastapi import APIRouter, HTTPException
from fastapi.responses import StreamingResponse

from models.schemas import ChatRequest, AIResponse
from services import chat_service
from utils.providers import require_api_key, require_provider

router = APIRouter(tags=["Chat"])


@router.post("/chat", response_model=AIResponse)
async def chat(payload: ChatRequest) -> AIResponse:
    """
    Gửi tin nhắn cho chatbot AI.

    Hỗ trợ:
    - providers: chatgpt, gemini, openrouter, claude, ollama
    - Context: khóa học, danh mục, khóa đang xem
    - History: lịch sử hội thoại
    - RAG: truy xuất giáo trình PTIT (khi đã ingest) cho role student
    """
    provider = require_provider(payload.provider)
    require_api_key(provider, payload.api_key)

    reply, tokens, rag_used, sources = await chat_service.chat(payload, role=payload.role)
    if not reply:
        raise HTTPException(
            status_code=502,
            detail="Provider AI không trả về nội dung.",
        )

    return AIResponse(
        reply=reply,
        tokens_used=tokens,
        rag_used=rag_used,
        sources=sources,
    )


@router.post("/chat/stream")
async def chat_stream(payload: ChatRequest) -> StreamingResponse:
    """
    Bản streaming của /chat — trả về Server-Sent Events (text/event-stream).

    Mỗi event là 1 dòng `data: {...}\\n\\n`:
    - {"delta": "..."}: đoạn văn bản mới
    - {"error": "..."}: lỗi trong lúc sinh (kết thúc stream ngay sau đó)
    - {"done": true, "tokens_used": {...}, "rag_used": bool, "sources": [...]}: event cuối
    """
    provider = require_provider(payload.provider)
    require_api_key(provider, payload.api_key)

    async def event_source():
        rag_used = False
        sources: list = []
        tokens_used = {"prompt": 0, "completion": 0, "total": 0}

        try:
            async for item in chat_service.chat_stream(payload, role=payload.role):
                if "delta" in item:
                    yield f"data: {json.dumps({'delta': item['delta']}, ensure_ascii=False)}\n\n"
                elif "rag" in item:
                    rag_used = bool(item["rag"].get("used"))
                    sources = item["rag"].get("sources") or []
                elif "usage" in item:
                    tokens_used = item["usage"]
        except HTTPException as exc:
            yield f"data: {json.dumps({'error': str(exc.detail)}, ensure_ascii=False)}\n\n"
            return
        except Exception as exc:  # noqa: BLE001 - luôn báo lỗi qua SSE, không để crash stream
            yield f"data: {json.dumps({'error': str(exc)}, ensure_ascii=False)}\n\n"
            return

        yield (
            "data: "
            + json.dumps(
                {"done": True, "tokens_used": tokens_used, "rag_used": rag_used, "sources": sources},
                ensure_ascii=False,
            )
            + "\n\n"
        )

    return StreamingResponse(event_source(), media_type="text/event-stream")
