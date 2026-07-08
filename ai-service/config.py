"""
Cấu hình ứng dụng AI Service.
Đọc từ biến môi trường, cung cấp giá trị mặc định hợp lý.
"""

import os


class Settings:
    """Cấu hình chung cho AI Service."""

    APP_TITLE: str = "Sylva LMS AI Service"
    APP_VERSION: str = "2.0.0"
    APP_DESCRIPTION: str = "AI Service cho hệ thống quản lý học tập Sylva LMS"

    # --- Provider defaults ---
    DEFAULT_OPENAI_MODEL: str = "gpt-4o-mini"
    DEFAULT_GEMINI_MODEL: str = "gemini-2.0-flash"
    DEFAULT_OPENROUTER_MODEL: str = "deepseek/deepseek-chat:free"
    DEFAULT_CLAUDE_MODEL: str = "claude-3-5-haiku-20241022"

    # --- Timeouts (seconds) ---
    OPENAI_TIMEOUT: int = 60
    GEMINI_TIMEOUT: int = 60
    OPENROUTER_TIMEOUT: int = 60
    CLAUDE_TIMEOUT: int = 60

    # --- Generation defaults ---
    DEFAULT_TEMPERATURE: float = 0.4
    GENERATION_TEMPERATURE: float = 0.7

    # --- Context limits ---
    MAX_COURSES_IN_CONTEXT: int = 12
    MAX_CATEGORIES_IN_CONTEXT: int = 8
    MAX_CATEGORY_CHILDREN: int = 4

    # --- ChromaDB settings ---
    CHROMADB_HOST: str = os.getenv("CHROMADB_HOST", "chromadb")
    CHROMADB_PORT: int = int(os.getenv("CHROMADB_PORT", "8000"))

    # --- Provider API URLs ---
    OPENAI_API_URL: str = "https://api.openai.com/v1/chat/completions"
    GEMINI_API_URL: str = "https://generativelanguage.googleapis.com/v1beta/models"
    OPENROUTER_API_URL: str = "https://openrouter.ai/api/v1/chat/completions"
    CLAUDE_API_URL: str = "https://api.anthropic.com/v1/messages"
    CLAUDE_API_VERSION: str = "2023-06-01"

    @classmethod
    def from_env(cls) -> "Settings":
        """Tạo Settings instance, override từ env nếu có."""
        settings = cls()
        for key in [
            "DEFAULT_OPENAI_MODEL",
            "DEFAULT_GEMINI_MODEL",
            "DEFAULT_OPENROUTER_MODEL",
            "DEFAULT_CLAUDE_MODEL",
        ]:
            env_val = os.getenv(key)
            if env_val:
                setattr(settings, key, env_val)
        return settings


settings = Settings.from_env()
