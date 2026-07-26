"""
Hàm format hiển thị dữ liệu.
"""

from __future__ import annotations

from models.schemas import CourseContext


def format_price(value: int | float | None) -> str:
    """Format giá tiền sang chuỗi tiếng Việt."""
    if not value or value <= 0:
        return "Miễn phí"
    amount = int(round(float(value)))
    return f"{amount:,}".replace(",", ".") + " đ"


def format_course_line(course: CourseContext) -> str:
    """Format thông tin 1 khóa học thành 1 dòng text."""
    parts = [
        f"- {course.title}",
        f"chuyên mục {course.category or 'không rõ'}",
        f"giảng viên {course.instructor or 'Eript LMS'}",
        f"học phí {format_price(course.price)}",
        f"{course.lessons_count or 0} bài học",
    ]
    if course.rating:
        parts.append(f"đánh giá {course.rating:.1f}/5")
    return ", ".join(parts)
