"""
Ingest giáo trình PDF → ChromaDB.

Nguồn khuyến nghị: https://github.com/0xl4p/Giao-Trinh-PTIT

Cách dùng:
  # Index PDF đã có trong rag/textbooks (không tải mạng)
  python -m rag.ingest --demo-pack

  # Tải thêm PDF vào data/textbooks (không commit)
  python -m rag.ingest --download --limit 8

  # Index mọi PDF local (data/textbooks)
  python -m rag.ingest --limit 0
"""

from __future__ import annotations

import argparse
import json
import sys
import urllib.parse
import urllib.request
from pathlib import Path

from rag.chunking import chunk_text, extract_pdf_text, subject_from_filename
from rag.paths import EVIDENCE_TEXTBOOKS_DIR, TEXTBOOKS_DIR, ensure_data_dirs
from rag.store import get_collection, get_collection_stats, list_indexed_sources, reset_collection

GITHUB_API = "https://api.github.com/repos/0xl4p/Giao-Trinh-PTIT/contents/"
GITHUB_RAW = "https://raw.githubusercontent.com/0xl4p/Giao-Trinh-PTIT/main/"
USER_AGENT = "Eript-LMS-RAG-Ingest/1.0"

# 1 môn / 1 ngành LMS — file gốc commit tại rag/textbooks/
DEMO_PACK = [
    {
        "faculty": "CNTT",
        "file": "Cấu trúc dữ liệu và giải thuật.pdf",
    },
    {
        "faculty": "QTKD",
        "file": "Marketing căn bản - 2017.pdf",
    },
    {
        "faculty": "ĐTVT",
        "file": "Điện tử số - 2013.pdf",
    },
]
DEMO_PACK_FILES = [item["file"] for item in DEMO_PACK]


def list_local_pdfs(directory: Path | None = None) -> list[Path]:
    ensure_data_dirs()
    root = directory or TEXTBOOKS_DIR
    return sorted(root.glob("*.pdf"), key=lambda p: p.name.lower())


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
    *,
    dest_dir: Path | None = None,
    exact_names: list[str] | None = None,
) -> list[Path]:
    """Tải PDF từ repo Giao-Trinh-PTIT (root)."""
    ensure_data_dirs()
    dest = dest_dir or TEXTBOOKS_DIR
    dest.mkdir(parents=True, exist_ok=True)

    req = urllib.request.Request(GITHUB_API, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=60) as resp:
        items = json.loads(resp.read().decode("utf-8"))

    pdfs = [
        it for it in items
        if it.get("type") == "file" and str(it.get("name", "")).lower().endswith(".pdf")
    ]
    if exact_names:
        wanted = set(exact_names)
        pdfs = [it for it in pdfs if it.get("name") in wanted]
    elif name_filters:
        filters = [f.lower() for f in name_filters]
        pdfs = [it for it in pdfs if any(f in str(it["name"]).lower() for f in filters)]
    pdfs.sort(key=lambda x: x["name"].lower())
    if limit > 0 and not exact_names and not name_filters:
        pdfs = pdfs[:limit]

    saved: list[Path] = []
    for it in pdfs:
        name = it["name"]
        target = dest / name
        if target.exists() and target.stat().st_size > 0 and not force:
            print(f"[skip] {name}")
            saved.append(target)
            continue

        url = _pdf_download_url(it)
        print(f"[download] {name}")
        try:
            import httpx
            with httpx.Client(timeout=180.0, follow_redirects=True, headers={"User-Agent": USER_AGENT}) as client:
                r = client.get(url)
                r.raise_for_status()
                target.write_bytes(r.content)
        except Exception:
            file_req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT, "Accept": "*/*"})
            with urllib.request.urlopen(file_req, timeout=180) as resp:
                target.write_bytes(resp.read())
        saved.append(target)
    return saved


def ingest_pdfs(
    pdfs: list[Path],
    *,
    chunk_size: int = 900,
    overlap: int = 150,
    max_pages: int | None = 80,
    batch_size: int = 64,
    skip_existing: bool = False,
) -> dict:
    """Index danh sách PDF vào Chroma."""
    col = get_collection(create=True)
    existing = list_indexed_sources() if skip_existing else set()
    total_chunks = 0
    docs_ok = 0
    docs_fail = 0
    docs_skip = 0

    for pdf in pdfs:
        if pdf.name in existing:
            print(f"[skip-indexed] {pdf.name}")
            docs_skip += 1
            continue
        try:
            print(f"[extract] {pdf.name}")
            raw = extract_pdf_text(pdf, max_pages=max_pages)
            chunks = chunk_text(raw, chunk_size=chunk_size, overlap=overlap)
            if not chunks:
                print(f"[warn] empty text: {pdf.name}")
                docs_fail += 1
                continue

            subject = subject_from_filename(pdf.name)
            faculty = next((item["faculty"] for item in DEMO_PACK if item["file"] == pdf.name), "")
            ids: list[str] = []
            documents: list[str] = []
            metadatas: list[dict] = []
            for idx, chunk in enumerate(chunks):
                ids.append(f"{pdf.stem}::{idx}")
                documents.append(chunk)
                meta = {
                    "source": pdf.name,
                    "subject": subject,
                    "chunk_index": idx,
                }
                if faculty:
                    meta["faculty"] = faculty
                metadatas.append(meta)

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
        "documents_skipped": docs_skip,
        "chunks_added": total_chunks,
        "collection": stats,
    }


def _prepare_demo_pack() -> list[Path]:
    """Đọc PDF minh chứng local tại rag/textbooks/ — không tải GitHub."""
    ensure_data_dirs()
    found = [
        path for path in list_local_pdfs(EVIDENCE_TEXTBOOKS_DIR)
        if path.stat().st_size > 0
    ]
    present = {p.name for p in found}
    for name in DEMO_PACK_FILES:
        if name not in present:
            print(f"[missing] {name} — đặt file vào {EVIDENCE_TEXTBOOKS_DIR}")
    if found:
        print("[evidence]", ", ".join(p.name for p in found))
    return found


def main(argv: list[str] | None = None) -> int:
    try:
        sys.stdout.reconfigure(encoding="utf-8")
    except Exception:
        pass

    parser = argparse.ArgumentParser(description="Ingest PTIT textbooks into ChromaDB for RAG")
    parser.add_argument(
        "--demo-pack",
        action="store_true",
        help="Index PDF minh chứng local tại rag/textbooks/ (không tải GitHub)",
    )
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
    parser.add_argument("--skip-existing", action="store_true", help="Bỏ qua PDF đã có trong Chroma")
    parser.add_argument("--max-pages", type=int, default=80, help="Giới hạn trang/PDF khi extract (0 = hết file)")
    parser.add_argument("--chunk-size", type=int, default=900)
    parser.add_argument("--overlap", type=int, default=150)
    args = parser.parse_args(argv)

    ensure_data_dirs()

    if args.demo_pack:
        pdfs = _prepare_demo_pack()
        if not pdfs:
            print("Không có PDF trong", EVIDENCE_TEXTBOOKS_DIR)
            print("Copy giáo trình vào thư mục đó rồi chạy lại --demo-pack.")
            return 1
        print("[demo-pack] reset collection rồi index PDF minh chứng")
        reset_collection()
        result = ingest_pdfs(
            pdfs,
            chunk_size=args.chunk_size,
            overlap=args.overlap,
            max_pages=args.max_pages if args.max_pages > 0 else None,
            skip_existing=False,
        )
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return 0

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
        print("Chạy: python -m rag.ingest --demo-pack")
        return 1

    if args.reset:
        print("[reset] collection")
        reset_collection()

    result = ingest_pdfs(
        pdfs,
        chunk_size=args.chunk_size,
        overlap=args.overlap,
        max_pages=args.max_pages if args.max_pages > 0 else None,
        skip_existing=args.skip_existing and not args.reset,
    )
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
