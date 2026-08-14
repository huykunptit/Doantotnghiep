"""ChromaDB persistent store cho giáo trình PTIT."""

from __future__ import annotations

from typing import Any

from rag.paths import CHROMA_DIR, COLLECTION_NAME, EVIDENCE_TEXTBOOKS_DIR, ensure_data_dirs


def _client():
    import chromadb
    from chromadb.config import Settings

    ensure_data_dirs()
    return chromadb.PersistentClient(
        path=str(CHROMA_DIR),
        settings=Settings(anonymized_telemetry=False),
    )


def get_collection(create: bool = True):
    client = _client()
    if create:
        return client.get_or_create_collection(
            name=COLLECTION_NAME,
            metadata={"hnsw:space": "cosine", "source": "Giao-Trinh-PTIT"},
        )
    return client.get_collection(name=COLLECTION_NAME)


def is_rag_ready() -> bool:
    try:
        col = get_collection(create=False)
        return col.count() > 0
    except Exception:
        return False


def list_subjects(limit: int = 5000) -> list[str]:
    """Danh sách tên môn (metadata subject) đã index."""
    try:
        col = get_collection(create=False)
        count = col.count()
        if count <= 0:
            return []
        sample = col.get(include=["metadatas"], limit=min(count, limit))
        subjects: set[str] = set()
        for meta in sample.get("metadatas") or []:
            if meta and meta.get("subject"):
                subjects.add(str(meta["subject"]).strip())
        return sorted(s for s in subjects if s)
    except Exception:
        return []


def list_evidence_pdfs() -> list[str]:
    """PDF minh chứng đang có trong rag/textbooks (source commit)."""
    ensure_data_dirs()
    return sorted(p.name for p in EVIDENCE_TEXTBOOKS_DIR.glob("*.pdf"))


def get_collection_stats() -> dict[str, Any]:
    try:
        col = get_collection(create=True)
        count = col.count()
        sources: set[str] = set()
        subjects: set[str] = set()
        if count > 0:
            # Lấy mẫu metadata để liệt kê số môn đã index.
            sample = col.get(include=["metadatas"], limit=min(count, 5000))
            for meta in sample.get("metadatas") or []:
                if not meta:
                    continue
                if meta.get("source"):
                    sources.add(str(meta["source"]))
                if meta.get("subject"):
                    subjects.add(str(meta["subject"]).strip())
        return {
            "ready": count > 0,
            "chunk_count": count,
            "document_count": len(sources),
            "documents": sorted(sources)[:200],
            "subjects": sorted(s for s in subjects if s)[:200],
            "persist_dir": str(CHROMA_DIR),
            "evidence_dir": str(EVIDENCE_TEXTBOOKS_DIR),
            "evidence_pdfs": list_evidence_pdfs(),
        }
    except Exception as exc:
        return {
            "ready": False,
            "chunk_count": 0,
            "document_count": 0,
            "documents": [],
            "subjects": [],
            "error": str(exc),
            "persist_dir": str(CHROMA_DIR),
            "evidence_dir": str(EVIDENCE_TEXTBOOKS_DIR),
            "evidence_pdfs": list_evidence_pdfs(),
        }


def list_indexed_sources() -> set[str]:
    """Tên file PDF đã có trong collection (để ingest bỏ qua, không ghi đè)."""
    try:
        col = get_collection(create=False)
        count = col.count()
        if count <= 0:
            return set()
        sample = col.get(include=["metadatas"], limit=min(count, 20000))
        names: set[str] = set()
        for meta in sample.get("metadatas") or []:
            if meta and meta.get("source"):
                names.add(str(meta["source"]))
        return names
    except Exception:
        return set()


def reset_collection() -> None:
    client = _client()
    try:
        client.delete_collection(COLLECTION_NAME)
    except Exception:
        pass
    get_collection(create=True)
