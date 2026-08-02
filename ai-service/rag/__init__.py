"""RAG: ingest giáo trình PDF → ChromaDB → retrieve khi chatbot sinh viên hỏi."""

from rag.retrieve import format_rag_context, retrieve_chunks
from rag.store import get_collection_stats, is_rag_ready

__all__ = [
    "format_rag_context",
    "retrieve_chunks",
    "get_collection_stats",
    "is_rag_ready",
]
