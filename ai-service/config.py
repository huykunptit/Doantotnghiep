"""
Cấu hình ứng dụng AI Service.
Đọc từ biến môi trường, cung cấp giá trị mặc định hợp lý.
"""

import os


class Settings:
    """Cấu hình chung cho AI Service."""

    APP_TITLE: str = "Eript LMS AI Service"
    APP_VERSION: str = "2.0.0"
    APP_DESCRIPTION: str = "AI Service cho hệ thống quản lý học tập Eript LMS"

    # --- Provider defaults ---
    DEFAULT_OPENAI_MODEL: str = "gpt-4o-mini"
    DEFAULT_GEMINI_MODEL: str = "gemini-2.0-flash"
    DEFAULT_OPENROUTER_MODEL: str = "deepseek/deepseek-chat:free"
    DEFAULT_CLAUDE_MODEL: str = "nghi/claude-haiku-4.5"
    DEFAULT_OLLAMA_MODEL: str = "qwen2.5:latest"

    # --- Timeouts (seconds) ---
    OPENAI_TIMEOUT: int = 60
    GEMINI_TIMEOUT: int = 60
    OPENROUTER_TIMEOUT: int = 60
    CLAUDE_TIMEOUT: int = 90
    OLLAMA_TIMEOUT: int = 180  # local CPU có thể chậm

    # --- Generation defaults ---
    DEFAULT_TEMPERATURE: float = 0.4
    GENERATION_TEMPERATURE: float = 0.7

    # --- Context limits ---
    MAX_COURSES_IN_CONTEXT: int = 12
    MAX_CATEGORIES_IN_CONTEXT: int = 8
    MAX_CATEGORY_CHILDREN: int = 4

    # --- RAG ---
    RAG_ENABLED: bool = True
    RAG_TOP_K: int = 5
    RAG_SOURCE_REPO: str = "https://github.com/0xl4p/Giao-Trinh-PTIT"

    # --- Provider API URLs ---
    OPENAI_API_URL: str = "https://api.openai.com/v1/chat/completions"
    GEMINI_API_URL: str = "https://generativelanguage.googleapis.com/v1beta/models"
    OPENROUTER_API_URL: str = "https://openrouter.ai/api/v1/chat/completions"
    # Claude qua proxy bên thứ 3 (nghimmo) — Anthropic Messages API
    CLAUDE_API_URL: str = "https://api.nghimmo.com/v1/messages"
    CLAUDE_API_VERSION: str = "2023-06-01"
    # bearer = Authorization: Bearer (gateway); x-api-key = official Anthropic
    CLAUDE_AUTH_MODE: str = "bearer"
    # Docker → Ollama trên Windows host
    OLLAMA_BASE_URL: str = "http://host.docker.internal:11434"

    # --- Xác thực nội bộ (chỉ backend Laravel được gọi) ---
    # Rỗng = không bắt buộc (dev local chưa cấu hình). Phải khớp AI_SERVICE_SHARED_SECRET
    # bên backend/.env khi triển khai thật.
    AI_SERVICE_SHARED_SECRET: str = ""

    @classmethod
    def from_env(cls) -> "Settings":
        """Tạo Settings instance, override từ env nếu có."""
        settings = cls()
        for key in [
            "DEFAULT_OPENAI_MODEL",
            "DEFAULT_GEMINI_MODEL",
            "DEFAULT_OPENROUTER_MODEL",
            "DEFAULT_CLAUDE_MODEL",
            "DEFAULT_OLLAMA_MODEL",
            "OLLAMA_BASE_URL",
            "CLAUDE_API_URL",
            "CLAUDE_AUTH_MODE",
            "CLAUDE_API_VERSION",
            "AI_SERVICE_SHARED_SECRET",
        ]:
            env_val = os.getenv(key)
            if env_val:
                setattr(settings, key, env_val)

        timeout = os.getenv("OLLAMA_TIMEOUT")
        if timeout and timeout.isdigit():
            settings.OLLAMA_TIMEOUT = int(timeout)

        return settings


settings = Settings.from_env()
