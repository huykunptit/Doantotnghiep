"""
Ingest giáo trình PDF → ChromaDB.

Nguồn khuyến nghị: https://github.com/0xl4p/Giao-Trinh-PTIT

Cách dùng:
  # 1) Tải vài PDF demo từ GitHub
  python -m rag.ingest --download --limit 8

  # 2) Hoặc copy PDF vào ai-service/data/textbooks rồi:
  python -m rag.ingest --limit 0   # 0 = tất cả file local

  # Reset index
  python -m rag.ingest --reset --download --limit 5
"""

from __future__ import annotations

import argparse
import json
import sys
import urllib.parse
import urllib.request
from pathlib import Path

from rag.chunking import chunk_text, extract_pdf_text, subject_from_filename
from rag.paths import TEXTBOOKS_DIR, ensure_data_dirs
from rag.store import get_collection, get_collection_stats, reset_collection

GITHUB_API = "https://api.github.com/repos/0xl4p/Giao-Trinh-PTIT/contents/"
GITHUB_RAW = "https://raw.githubusercontent.com/0xl4p/Giao-Trinh-PTIT/main/"
USER_AGENT = "Eript-LMS-RAG-Ingest/1.0"


def list_local_pdfs() -> list[Path]:
    ensure_data_dirs()
    return sorted(TEXTBOOKS_DIR.glob("*.pdf"), key=lambda p: p.name.lower())


def _pdf_download_url(item: dict) -> str:
    """Ưu tiên download_url từ API; fallback raw URL đã encode."""
    url = item.get("download_url")
    if isinstance(url, str) and url.startswith("http"):
        return url
    name = str(item.get("name") or "")
    return GITHUB_RAW + urllib.parse.quote(name, safe="")


def download_from_github(
    limit: int = 8,
    force: bool = False,
    name_filters: list[str] | None = None,
) -> list[Path]:
    """Tải PDF từ repo Giao-Trinh-PTIT (root)."""
    ensure_data_dirs()
    req = urllib.request.Request(GITHUB_API, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=60) as resp:
        items = json.loads(resp.read().decode("utf-8"))

    pdfs = [
        it for it in items
        if it.get("type") == "file" and str(it.get("name", "")).lower().endswith(".pdf")
    ]
    if name_filters:
        filters = [f.lower() for f in name_filters]
        pdfs = [it for it in pdfs if any(f in str(it["name"]).lower() for f in filters)]
    pdfs.sort(key=lambda x: x["name"].lower())
    if limit > 0 and not name_filters:
        pdfs = pdfs[:limit]

    saved: list[Path] = []
    for it in pdfs:
        name = it["name"]
        dest = TEXTBOOKS_DIR / name
        if dest.exists() and dest.stat().st_size > 0 and not force:
            print(f"[skip] {name}")
            saved.append(dest)
            continue

        url = _pdf_download_url(it)
        print(f"[download] {name}")
        # httpx xử lý Unicode URL ổn định hơn urllib trên Windows
        try:
            import httpx
            with httpx.Client(timeout=180.0, follow_redirects=True, headers={"User-Agent": USER_AGENT}) as client:
                r = client.get(url)
                r.raise_for_status()
                dest.write_bytes(r.content)
        except Exception:
            file_req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT, "Accept": "*/*"})
            with urllib.request.urlopen(file_req, timeout=180) as resp:
                dest.write_bytes(resp.read())
        saved.append(dest)
    return saved


def ingest_pdfs(
    pdfs: list[Path],
    *,
    chunk_size: int = 900,
    overlap: int = 150,
    max_pages: int | None = 80,
    batch_size: int = 64,
) -> dict:
    """Index danh sách PDF vào Chroma."""
    col = get_collection(create=True)
    total_chunks = 0
    docs_ok = 0
    docs_fail = 0

    for pdf in pdfs:
        try:
            print(f"[extract] {pdf.name}")
            raw = extract_pdf_text(pdf, max_pages=max_pages)
            chunks = chunk_text(raw, chunk_size=chunk_size, overlap=overlap)
            if not chunks:
                print(f"[warn] empty text: {pdf.name}")
                docs_fail += 1
                continue

            subject = subject_from_filename(pdf.name)
            ids: list[str] = []
            documents: list[str] = []
            metadatas: list[dict] = []
            for idx, chunk in enumerate(chunks):
                ids.append(f"{pdf.stem}::{idx}")
                documents.append(chunk)
                metadatas.append({
                    "source": pdf.name,
                    "subject": subject,
                    "chunk_index": idx,
                })

            # upsert theo batch
            for i in range(0, len(ids), batch_size):
                col.upsert(
                    ids=ids[i:i + batch_size],
                    documents=documents[i:i + batch_size],
                    metadatas=metadatas[i:i + batch_size],
                )
            total_chunks += len(chunks)
            docs_ok += 1
            print(f"[ok] {pdf.name}: {len(chunks)} chunks | subject={subject}")
        except Exception as exc:
            docs_fail += 1
            print(f"[error] {pdf.name}: {exc}")

    stats = get_collection_stats()
    return {
        "documents_ok": docs_ok,
        "documents_fail": docs_fail,
        "chunks_added": total_chunks,
        "collection": stats,
    }


def main(argv: list[str] | None = None) -> int:
    # Windows console
    try:
        sys.stdout.reconfigure(encoding="utf-8")
    except Exception:
        pass

    parser = argparse.ArgumentParser(description="Ingest PTIT textbooks into ChromaDB for RAG")
    parser.add_argument("--download", action="store_true", help="Tải PDF từ GitHub Giao-Trinh-PTIT")
    parser.add_argument("--limit", type=int, default=8, help="Số PDF tải/index (0 = tất cả local)")
    parser.add_argument(
        "--files",
        nargs="*",
        default=None,
        help="Chỉ tải/index các tên file PDF cụ thể (khớp substring)",
    )
    parser.add_argument("--force-download", action="store_true", help="Tải lại dù file đã có")
    parser.add_argument("--reset", action="store_true", help="Xóa collection trước khi ingest")
    parser.add_argument("--max-pages", type=int, default=80, help="Giới hạn trang/PDF khi extract")
    parser.add_argument("--chunk-size", type=int, default=900)
    parser.add_argument("--overlap", type=int, default=150)
    args = parser.parse_args(argv)

    ensure_data_dirs()

    if args.download:
        download_from_github(
            limit=args.limit if args.limit > 0 else 50,
            force=args.force_download,
            name_filters=args.files,
        )

    pdfs = list_local_pdfs()
    if args.files:
        filters = [f.lower() for f in args.files]
        pdfs = [p for p in pdfs if any(f in p.name.lower() for f in filters)]
    elif args.limit > 0:
        pdfs = pdfs[: args.limit]

    if not pdfs:
        print("Không có PDF trong", TEXTBOOKS_DIR)
        print("Chạy: python -m rag.ingest --download --limit 8")
        return 1

    if args.reset:
        print("[reset] collection")
        reset_collection()

    result = ingest_pdfs(
        pdfs,
        chunk_size=args.chunk_size,
        overlap=args.overlap,
        max_pages=args.max_pages if args.max_pages > 0 else None,
    )
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
