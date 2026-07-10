"""
RAG Vector Store — ChromaDB wrapper.

Collection naming: "course_{course_id}" hoặc "subject_{subject_slug}"
cho các tài liệu từ repo PTIT.
"""

from __future__ import annotations

import logging
from typing import Optional

import chromadb
from chromadb.config import Settings

from config import settings as app_settings

logger = logging.getLogger(__name__)

# Singleton client
_client: Optional[chromadb.HttpClient] = None


def _get_client() -> chromadb.HttpClient:
    """Lấy ChromaDB HTTP client (singleton)."""
    global _client
    if _client is None:
        _client = chromadb.HttpClient(
            host=app_settings.CHROMADB_HOST,
            port=app_settings.CHROMADB_PORT,
            settings=Settings(anonymized_telemetry=False),
        )
        logger.info(f"ChromaDB connected: {app_settings.CHROMADB_HOST}:{app_settings.CHROMADB_PORT}")
    return _client


def get_or_create_collection(name: str) -> chromadb.Collection:
    """Lấy hoặc tạo mới collection theo tên."""
    client = _get_client()
    return client.get_or_create_collection(
        name=_sanitize_name(name),
        metadata={"hnsw:space": "cosine"},  # Dùng cosine similarity
    )


def add_documents(
    collection_name: str,
    documents: list[str],
    embeddings: list[list[float]],
    metadatas: list[dict],
    ids: list[str],
) -> None:
    """
    Thêm documents vào collection.
    
    Args:
        collection_name: Tên collection
        documents: Danh sách text chunks
        embeddings: Danh sách embedding vectors
        metadatas: Danh sách metadata cho từng chunk
        ids: Danh sách unique ID cho từng chunk
    """
    collection = get_or_create_collection(collection_name)
    
    # Chia batch để tránh quá tải
    batch_size = 100
    for i in range(0, len(documents), batch_size):
        batch_docs = documents[i:i + batch_size]
        batch_embs = embeddings[i:i + batch_size]
        batch_metas = metadatas[i:i + batch_size]
        batch_ids = ids[i:i + batch_size]

        # Sanitize metadata (ChromaDB chỉ chấp nhận str, int, float, bool)
        batch_metas = [_sanitize_metadata(m) for m in batch_metas]

        collection.add(
            documents=batch_docs,
            embeddings=batch_embs,
            metadatas=batch_metas,
            ids=batch_ids,
        )
    
    logger.info(f"Added {len(documents)} docs to collection '{collection_name}'")


def query_collection(
    collection_name: str,
    query_embedding: list[float],
    n_results: int = 5,
    where: dict | None = None,
) -> list[dict]:
    """
    Truy vấn tài liệu tương tự nhất.
    
    Returns:
        List of {content, metadata, distance, id}
    """
    try:
        collection = get_or_create_collection(collection_name)
        count = collection.count()
        if count == 0:
            logger.warning(f"Collection '{collection_name}' is empty")
            return []

        n_results = min(n_results, count)
        kwargs = {
            "query_embeddings": [query_embedding],
            "n_results": n_results,
            "include": ["documents", "metadatas", "distances"],
        }
        if where:
            kwargs["where"] = where

        results = collection.query(**kwargs)

        docs = results.get("documents", [[]])[0]
        metas = results.get("metadatas", [[]])[0]
        dists = results.get("distances", [[]])[0]
        ids = results.get("ids", [[]])[0]

        return [
            {
                "content": doc,
                "metadata": meta,
                "distance": dist,
                "id": id_,
            }
            for doc, meta, dist, id_ in zip(docs, metas, dists, ids)
        ]
    except Exception as e:
        logger.error(f"Query collection '{collection_name}' failed: {e}")
        return []


def list_collections() -> list[dict]:
    """Liệt kê tất cả collections và thông tin cơ bản."""
    client = _get_client()
    collections = client.list_collections()
    result = []
    for col in collections:
        try:
            result.append({
                "name": col.name,
                "count": col.count(),
                "metadata": col.metadata or {},
            })
        except Exception:
            result.append({"name": col.name, "count": 0, "metadata": {}})
    return result


def delete_collection(name: str) -> bool:
    """Xóa một collection."""
    try:
        client = _get_client()
        client.delete_collection(_sanitize_name(name))
        logger.info(f"Deleted collection '{name}'")
        return True
    except Exception as e:
        logger.error(f"Delete collection '{name}' failed: {e}")
        return False


def collection_exists(name: str) -> bool:
    """Kiểm tra collection có tồn tại không."""
    client = _get_client()
    try:
        client.get_collection(_sanitize_name(name))
        return True
    except Exception:
        return False


def _sanitize_name(name: str) -> str:
    """Chuẩn hóa tên collection (chỉ cho phép alphanumeric và underscore)."""
    import re
    # Thay thế ký tự không hợp lệ bằng underscore
    name = re.sub(r'[^a-zA-Z0-9_-]', '_', name)
    # ChromaDB yêu cầu tên >= 3 ký tự
    if len(name) < 3:
        name = name + "_col"
    return name[:63]  # Max 63 chars


def _sanitize_metadata(meta: dict) -> dict:
    """Đảm bảo metadata chỉ chứa str, int, float, bool."""
    result = {}
    for k, v in meta.items():
        if isinstance(v, (str, int, float, bool)):
            result[str(k)] = v
        elif v is None:
            result[str(k)] = ""
        else:
            result[str(k)] = str(v)
    return result
