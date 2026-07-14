FROM python:3.12-slim

WORKDIR /app

# ── System build tools ──────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .

# ── Layer 1: torch CPU-only (MUST come before sentence-transformers) ─────────
# Using the PyTorch CPU index prevents sentence-transformers from pulling the
# GPU variant (~2 GB) when it resolves its own torch dependency later.
RUN pip install --no-cache-dir \
    "torch>=2.0.0,<3.0.0" \
    --index-url https://download.pytorch.org/whl/cpu

# ── Layer 2: chromadb + its heavy deps (separate layer for caching) ──────────
RUN pip install --no-cache-dir \
    --extra-index-url https://download.pytorch.org/whl/cpu \
    "chromadb>=0.4.24"

# ── Layer 3: sentence-transformers (torch already satisfied → no GPU pull) ───
RUN pip install --no-cache-dir \
    --extra-index-url https://download.pytorch.org/whl/cpu \
    "sentence-transformers>=2.5.1"

# ── Layer 4: remaining lightweight requirements ──────────────────────────────
RUN pip install --no-cache-dir \
    --extra-index-url https://download.pytorch.org/whl/cpu \
    "fastapi==0.116.1" \
    "uvicorn[standard]==0.35.0" \
    "httpx>=0.28.0" \
    "pydantic>=2.10.0" \
    "PyPDF2>=3.0.1" \
    "python-docx>=1.1.0" \
    "tiktoken>=0.6.0" \
    "python-multipart>=0.0.9"

# ── Pre-cache embedding model inside the image ───────────────────────────────
ENV HF_HOME=/root/.cache/huggingface
RUN python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('all-MiniLM-L6-v2')"

# ── Copy application code ────────────────────────────────────────────────────
COPY . .

EXPOSE 8001

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8001", "--reload"]
