"""
RAG Retriever — Truy xuất tài liệu liên quan cho câu hỏi.
"""

from __future__ import annotations

import logging

from services.rag import embedder, vector_store

logger = logging.getLogger(__name__)

# Ngưỡng độ tương đồng tối thiểu (cosine distance <= 0.6 = tương đồng >= 40%)
MAX_DISTANCE_THRESHOLD = 0.65

# Collection tổng hợp tất cả tài liệu PTIT
GLOBAL_COLLECTION = "ptit_all_subjects"


def retrieve(
    question: str,
    collection_name: str | None = None,
    top_k: int = 5,
    course_id: int | None = None,
) -> list[dict]:
    """
    Tìm các chunks tài liệu liên quan nhất đến câu hỏi.
    
    Args:
        question: Câu hỏi từ người dùng
        collection_name: Tên collection (nếu None, tìm trong global collection)
        top_k: Số chunks trả về
        course_id: ID khóa học để filter (optional)
    
    Returns:
        List of {content, metadata, distance, id, relevance_score}
    """
    if not question.strip():
        return []

    # Tạo embedding cho câu hỏi
    query_embedding = embedder.embed_text(question)

    # Xác định collection để query
    target_collection = collection_name or GLOBAL_COLLECTION

    # Filter theo course nếu có
    where_filter = None
    if course_id:
        where_filter = {"course_id": course_id}

    # Query vector store
    results = vector_store.query_collection(
        collection_name=target_collection,
        query_embedding=query_embedding,
        n_results=top_k,
        where=where_filter,
    )

    # Nếu không có kết quả từ collection cụ thể, thử global collection
    if not results and target_collection != GLOBAL_COLLECTION:
        logger.info(f"No results in '{target_collection}', falling back to global collection")
        results = vector_store.query_collection(
            collection_name=GLOBAL_COLLECTION,
            query_embedding=query_embedding,
            n_results=top_k,
        )

    # Filter theo ngưỡng tương đồng và thêm relevance score
    filtered = []
    for r in results:
        dist = r.get("distance", 1.0)
        if dist <= MAX_DISTANCE_THRESHOLD:
            r["relevance_score"] = round((1 - dist) * 100, 1)  # Convert to % score
            filtered.append(r)

    logger.debug(f"Retrieved {len(filtered)}/{len(results)} relevant chunks for: '{question[:50]}...'")
    return filtered


def retrieve_for_chat(
    question: str,
    course_id: int | None = None,
    subject_name: str | None = None,
    top_k: int = 4,
) -> list[dict]:
    """
    Phiên bản retrieval tối ưu cho chatbot.
    Thử nhiều collections theo thứ tự ưu tiên.
    """
    results = []

    # 1. Thử collection theo môn học cụ thể
    if subject_name:
        slug = _subject_to_slug(subject_name)
        results = retrieve(question, f"subject_{slug}", top_k=top_k)

    # 2. Thử theo course_id
    if not results and course_id:
        results = retrieve(question, f"course_{course_id}", top_k=top_k)

    # 3. Fallback: global collection
    if not results:
        results = retrieve(question, GLOBAL_COLLECTION, top_k=top_k)

    return results


def format_context_for_prompt(chunks: list[dict]) -> str:
    """
    Format chunks thành chuỗi context cho LLM prompt.
    """
    if not chunks:
        return ""

    lines = ["📚 TÀI LIỆU THAM KHẢO:"]
    for i, chunk in enumerate(chunks, 1):
        meta = chunk.get("metadata", {})
        source = meta.get("source_file", "Tài liệu PTIT")
        score = chunk.get("relevance_score", 0)
        lines.append(f"\n[{i}] Nguồn: {source} (độ liên quan: {score:.0f}%)")
        lines.append(chunk["content"])

    return "\n".join(lines)


def _subject_to_slug(subject_name: str) -> str:
    """Chuyển tên môn thành slug cho collection name."""
    import re
    import unicodedata
    
    # Normalize Unicode (remove diacritics)
    normalized = unicodedata.normalize('NFKD', subject_name)
    ascii_str = normalized.encode('ascii', 'ignore').decode('ascii')
    slug = re.sub(r'[^a-z0-9]+', '_', ascii_str.lower()).strip('_')
    return slug[:40]
