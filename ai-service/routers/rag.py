"""
RAG Router — Quản lý tài liệu và truy vấn vector database.

Endpoints:
- POST /rag/ingest     — Upload + ingest tài liệu
- POST /rag/query      — Truy vấn chunks liên quan
- GET  /rag/collections — Liệt kê collections
- DELETE /rag/collections/{name} — Xóa collection
"""

from __future__ import annotations

import logging
import os
import tempfile

from fastapi import APIRouter, HTTPException, UploadFile, File, Form

from models.schemas import (
    RagIngestRequest,
    RagIngestResponse,
    RagQueryRequest,
    RagQueryResponse,
    RagCollectionInfo,
)
from services.rag import ingest as ingest_service
from services.rag import retriever as retriever_service
from services.rag import vector_store

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/rag", tags=["RAG"])


@router.post("/ingest/url", response_model=RagIngestResponse)
async def ingest_from_url(payload: RagIngestRequest) -> RagIngestResponse:
    """
    Ingest tài liệu từ URL (GitHub raw download).
    Dùng cho admin import tài liệu từ repo PTIT.
    """
    import httpx

    try:
        async with httpx.AsyncClient(timeout=60) as client:
            response = await client.get(payload.file_url)
            response.raise_for_status()
            content = response.content
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Không tải được file: {e}")

    # Lưu tạm vào disk
    ext = ".pdf" if payload.file_url.lower().endswith(".pdf") else ".docx"
    with tempfile.NamedTemporaryFile(suffix=ext, delete=False) as tmp:
        tmp.write(content)
        tmp_path = tmp.name

    try:
        collection_name = payload.collection_name or ingest_service.subject_name_to_collection(payload.subject_name or "unknown")
        metadata = {
            "subject_name": payload.subject_name or "",
            "course_id": payload.course_id or 0,
            "file_url": payload.file_url,
        }

        if ext == ".pdf":
            result = ingest_service.ingest_pdf(tmp_path, collection_name, metadata)
        else:
            result = ingest_service.ingest_docx(tmp_path, collection_name, metadata)

    finally:
        os.unlink(tmp_path)

    if not result["success"]:
        raise HTTPException(status_code=500, detail=result.get("error", "Ingest thất bại"))

    return RagIngestResponse(
        success=True,
        chunks_added=result["chunks_added"],
        collection_name=collection_name,
        message=f"Đã ingest {result['chunks_added']} chunks từ '{payload.subject_name or 'tài liệu'}'",
    )


@router.post("/ingest/upload", response_model=RagIngestResponse)
async def ingest_upload(
    file: UploadFile = File(...),
    subject_name: str = Form(""),
    course_id: int = Form(0),
    collection_name: str = Form(""),
) -> RagIngestResponse:
    """Upload và ingest file PDF/DOCX trực tiếp."""
    if not file.filename:
        raise HTTPException(status_code=400, detail="Không có file")

    filename = file.filename.lower()
    if not (filename.endswith(".pdf") or filename.endswith(".docx")):
        raise HTTPException(status_code=400, detail="Chỉ hỗ trợ PDF và DOCX")

    content = await file.read()
    ext = ".pdf" if filename.endswith(".pdf") else ".docx"

    with tempfile.NamedTemporaryFile(suffix=ext, delete=False) as tmp:
        tmp.write(content)
        tmp_path = tmp.name

    try:
        col_name = collection_name or ingest_service.subject_name_to_collection(subject_name or file.filename)
        metadata = {
            "subject_name": subject_name,
            "course_id": course_id,
            "original_filename": file.filename,
        }

        if ext == ".pdf":
            result = ingest_service.ingest_pdf(tmp_path, col_name, metadata)
        else:
            result = ingest_service.ingest_docx(tmp_path, col_name, metadata)

    finally:
        os.unlink(tmp_path)

    if not result["success"]:
        raise HTTPException(status_code=500, detail=result.get("error", "Ingest thất bại"))

    return RagIngestResponse(
        success=True,
        chunks_added=result["chunks_added"],
        collection_name=col_name,
        message=f"Đã ingest {result['chunks_added']} chunks từ '{file.filename}'",
    )


@router.post("/query", response_model=RagQueryResponse)
def query_rag(payload: RagQueryRequest) -> RagQueryResponse:
    """Truy vấn tài liệu liên quan cho câu hỏi."""
    chunks = retriever_service.retrieve_for_chat(
        question=payload.question,
        course_id=payload.course_id,
        subject_name=payload.subject_name,
        top_k=payload.top_k or 5,
    )

    sources = [
        {
            "content": c["content"][:300],  # Preview
            "source_file": c["metadata"].get("source_file", ""),
            "subject_name": c["metadata"].get("subject_name", ""),
            "relevance_score": c.get("relevance_score", 0),
        }
        for c in chunks
    ]

    return RagQueryResponse(
        chunks_found=len(chunks),
        sources=sources,
        context_text=retriever_service.format_context_for_prompt(chunks),
    )


@router.get("/collections", response_model=list[RagCollectionInfo])
def list_collections() -> list[RagCollectionInfo]:
    """Liệt kê tất cả collections trong ChromaDB."""
    cols = vector_store.list_collections()
    return [
        RagCollectionInfo(
            name=c["name"],
            document_count=c["count"],
        )
        for c in cols
    ]


@router.delete("/collections/{name}")
def delete_collection_endpoint(name: str) -> dict:
    """Xóa một collection."""
    success = vector_store.delete_collection(name)
    if not success:
        raise HTTPException(status_code=404, detail=f"Collection '{name}' không tồn tại")
    return {"message": f"Đã xóa collection '{name}'"}
