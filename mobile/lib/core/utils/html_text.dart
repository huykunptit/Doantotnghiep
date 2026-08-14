/// Turns CMS HTML (or mixed HTML/plain text) into readable plain text.
String htmlToPlainText(String? raw) {
  if (raw == null) return '';
  var text = raw.trim();
  if (text.isEmpty) return '';
  if (!text.contains('<') && !text.contains('&')) return text;

  text = text.replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n');
  text = text.replaceAll(RegExp(r'</p\s*>', caseSensitive: false), '\n\n');
  text = text.replaceAll(RegExp(r'</h[1-6]\s*>', caseSensitive: false), '\n\n');
  text = text.replaceAll(RegExp(r'</li\s*>', caseSensitive: false), '\n');
  text = text.replaceAll(RegExp(r'<li[^>]*>', caseSensitive: false), '• ');
  text = text.replaceAll(RegExp(r'<[^>]+>'), '');
  text = text
      .replaceAll('&nbsp;', ' ')
      .replaceAll('&amp;', '&')
      .replaceAll('&lt;', '<')
      .replaceAll('&gt;', '>')
      .replaceAll('&quot;', '"')
      .replaceAll('&#39;', "'")
      .replaceAll('&apos;', "'");
  text = text.replaceAll(RegExp(r'[ \t]+\n'), '\n');
  text = text.replaceAll(RegExp(r'\n{3,}'), '\n\n');
  return text.trim();
}

/// Shortens lesson titles that repeat the full course name.
String displayLessonTitle(String lessonTitle, String courseTitle) {
  final course = courseTitle.trim();
  if (course.isEmpty) return lessonTitle.trim();
  var title = lessonTitle.trim();
  for (final needle in [' khóa $course', ' $course', '($course)']) {
    title = title.replaceAll(needle, '');
  }
  title = title.replaceAll(RegExp(r'\s{2,}'), ' ').trim();
  return title.isEmpty ? lessonTitle.trim() : title;
}
