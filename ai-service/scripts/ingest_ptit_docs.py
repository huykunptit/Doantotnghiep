"""
Script ingest_ptit_docs.py — Tải và ingest tài liệu từ repo GitHub Giao-Trinh-PTIT vào ChromaDB local.
"""

from __future__ import annotations

import os
import re
import urllib.parse
import sys
import logging

import httpx

# Thêm thư mục cha vào PYTHONPATH để import services
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from services.rag import ingest as ingest_service
from services.rag import vector_store

logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")
logger = logging.getLogger(__name__)

REPO_API_URL = "https://api.github.com/repos/0xl4p/Giao-Trinh-PTIT/git/trees/main?recursive=1"
RAW_BASE_URL = "https://raw.githubusercontent.com/0xl4p/Giao-Trinh-PTIT/main/"

# Danh sách một số môn học cốt lõi để test RAG trước
CORE_SUBJECTS = [
    "Cấu trúc dữ liệu và giải thuật",
    "Ngôn ngữ lập trình C++",
    "Ngôn ngữ lập trình Java",
    "Bài giảng Lập trình hướng đối tượng",
    "Nhập môn trí tuệ nhân tạo",
    "Mạng máy tính và internet",
    "Giáo trình hệ điều hành",
    "Nhập môn Công nghệ phần mềm",
]


def get_all_pdf_files() -> list[dict]:
    """Lấy danh sách files PDF từ GitHub API."""
    logger.info("Đang lấy danh sách file từ GitHub repository...")
    try:
        response = httpx.get(REPO_API_URL, timeout=30)
        response.raise_for_status()
        tree = response.json().get("tree", [])

        pdfs = []
        for item in tree:
            path = item.get("path", "")
            if path.lower().endswith(".pdf") and item.get("type") == "blob":
                pdfs.append({
                    "path": path,
                    "size": item.get("size", 0),
                })
        logger.info(f"Tìm thấy {len(pdfs)} file PDF trong repository.")
        return pdfs
    except Exception as e:
        logger.error(f"Không thể kết nối GitHub API: {e}")
        return []


def download_and_ingest_file(file_info: dict) -> bool:
    """Tải 1 file PDF và ingest vào ChromaDB."""
    path = file_info["path"]
    encoded_path = urllib.parse.quote(path)
    download_url = f"{RAW_BASE_URL}{encoded_path}"

    logger.info(f"Đang tải: {path} ({file_info['size'] / (1024*1024):.2f} MB)...")

    # Tạo thư mục temp
    temp_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "temp")
    os.makedirs(temp_dir, exist_ok=True)
    temp_file_path = os.path.join(temp_dir, os.path.basename(path))

    try:
        # Download file
        with httpx.stream("GET", download_url, timeout=120) as r:
            r.raise_for_status()
            with open(temp_file_path, "wb") as f:
                for chunk in r.iter_bytes():
                    f.write(chunk)

        # Trích xuất tên môn học sạch
        subject_name = clean_subject_name(path)
        collection_name = ingest_service.subject_name_to_collection(subject_name)

        metadata = {
            "subject_name": subject_name,
            "source_file": os.path.basename(path),
            "course_id": 0,  # Sẽ map với Course ID của DB sau
        }

        logger.info(f"Đang ingest '{subject_name}' vào collection '{collection_name}'...")
        result = ingest_service.ingest_pdf(temp_file_path, collection_name, metadata)

        if result["success"]:
            logger.info(f"✅ Thành công! Đã thêm {result['chunks_added']} chunks cho môn '{subject_name}'")
            return True
        else:
            logger.error(f"❌ Ingest thất bại cho '{path}': {result.get('error')}")
            return False

    except Exception as e:
        logger.error(f"❌ Lỗi xử lý file '{path}': {e}")
        return False
    finally:
        # Dọn dẹp temp
        if os.path.exists(temp_file_path):
            os.unlink(temp_file_path)


def clean_subject_name(filename: str) -> str:
    """Trích xuất tên môn học sạch từ tên file."""
    # Loại bỏ đuôi .pdf
    name = os.path.splitext(filename)[0]
    # Loại bỏ tiền tố bài giảng, giáo trình, bài tập
    name = re.sub(r'^(Bài giảng|Giáo trình|Bài tập|BG|Bìa giảng)\s+', '', name, flags=re.IGNORECASE)
    # Loại bỏ năm học hoặc hậu tố số phiên bản (ví dụ: - 2017, - 2013)
    name = re.sub(r'\s*-\s*\d{4}.*$', '', name)
    return name.strip()


def main():
    # Kiểm tra ChromaDB connection
    try:
        cols = vector_store.list_collections()
        logger.info(f"Kết nối ChromaDB thành công. Hiện tại có {len(cols)} collections.")
    except Exception as e:
        logger.error(f"Không thể kết nối ChromaDB. Hãy đảm bảo ChromaDB service đang chạy: {e}")
        sys.exit(1)

    pdfs = get_all_pdf_files()
    if not pdfs:
        logger.error("Không có file nào để ingest.")
        sys.exit(1)

    # Lọc ra các file thuộc Core Subjects để demo nhanh
    core_pdfs = []
    for pdf in pdfs:
        cleaned = clean_subject_name(pdf["path"])
        if any(core.lower() in cleaned.lower() for core in CORE_SUBJECTS):
            core_pdfs.append(pdf)

    logger.info(f"Tìm thấy {len(core_pdfs)} file cốt lõi trong danh sách Core Subjects.")

    # Cho phép chọn ingest hết hoặc chỉ core
    to_ingest = core_pdfs if core_pdfs else pdfs[:5]  # Fallback ingest 5 file đầu nếu ko khớp

    success_count = 0
    for pdf in to_ingest:
        if download_and_ingest_file(pdf):
            success_count += 1

    logger.info(f"🎉 Hoàn thành ingest! Ingest thành công {success_count}/{len(to_ingest)} files tài liệu.")


if __name__ == "__main__":
    main()
