"""
Xây dựng context summary từ dữ liệu khóa học + danh mục.
"""

from __future__ import annotations

from config import settings
from models.schemas import ChatContext
from utils.formatting import format_course_line


def build_context_summary(context: ChatContext) -> str:
    """Tạo text tóm tắt context cho system prompt."""
    lines: list[str] = []

    if context.current_course:
        lines.append("KHOA_HOC_DANG_XEM:")
        lines.append(format_course_line(context.current_course))

    if context.categories:
        lines.append("DANH_MUC:")
        for category in context.categories[: settings.MAX_CATEGORIES_IN_CONTEXT]:
            child_names = ", ".join(
                child.name
                for child in category.children[: settings.MAX_CATEGORY_CHILDREN]
            )
            label = f"- {category.name}"
            if child_names:
                label += f" | nhánh con: {child_names}"
            lines.append(label)

    if context.courses:
        lines.append("KHOA_HOC_NOI_BAT:")
        for course in context.courses[: settings.MAX_COURSES_IN_CONTEXT]:
            lines.append(format_course_line(course))

    return "\n".join(lines).strip()
