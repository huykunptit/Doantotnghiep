"""RAG admin/status endpoints."""

from __future__ import annotations

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from rag.retrieve import retrieve_chunks
from rag.store import get_collection_stats

router = APIRouter(prefix="/rag", tags=["RAG"])


class RagQueryRequest(BaseModel):
    query: str = Field(min_length=1, max_length=1000)
    top_k: int = Field(default=5, ge=1, le=12)
    subject_hint: str | None = None
    # global | course
    scope: str = Field(default="global")


@router.get("/status")
def rag_status():
    """Thống kê vector store giáo trình."""
    return get_collection_stats()


@router.post("/query")
def rag_query(payload: RagQueryRequest):
    """Thử retrieval (debug / admin)."""
    stats = get_collection_stats()
    if not stats.get("ready"):
        raise HTTPException(
            status_code=503,
            detail="RAG chưa sẵn sàng. Chạy: python -m rag.ingest --download --limit 8",
        )
    scope = (payload.scope or "global").strip().lower()
    if scope not in {"global", "course"}:
        scope = "global"
    hits = retrieve_chunks(
        payload.query,
        top_k=payload.top_k,
        subject_hint=payload.subject_hint,
        scope=scope,  # type: ignore[arg-type]
    )
    return {
        "query": payload.query,
        "scope": scope,
        "subject_hint": payload.subject_hint,
        "hits": hits,
        "stats": stats,
    }
