"""Kiểm tra đối chiếu diễn giải với ngữ cảnh hồ sơ."""

from __future__ import annotations

import unittest

from utils.grounding import unknown_quoted_titles


class GroundingTest(unittest.TestCase):
    def test_known_title_is_not_flagged(self) -> None:
        text = 'Bạn nên ôn lại "Cơ sở dữ liệu" trước kỳ tới.'
        self.assertEqual(unknown_quoted_titles(text, ["Cơ sở dữ liệu"]), [])

    def test_unknown_quoted_title_is_flagged(self) -> None:
        text = 'Hãy học thêm "Quantum Computing 101" ngay.'
        found = unknown_quoted_titles(text, ["Cơ sở dữ liệu"])
        self.assertEqual(found, ["Quantum Computing 101"])


if __name__ == "__main__":
    unittest.main()
