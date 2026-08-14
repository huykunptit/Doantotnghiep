import '../api/api_constants.dart';

/// Backend media endpoints (thumbnails, avatars, certificate backgrounds, ...)
/// return host-relative paths like `/storage/xxx.jpg` — valid when rendered
/// by the web frontend (same origin as the API), but not a loadable URL for
/// Flutter's network image widgets, which require an absolute URL.
///
/// Resolves a possibly-relative media path into an absolute URL using the
/// configured API host. Returns null/absolute URLs unchanged.
String? resolveMediaUrl(String? path) {
  if (path == null || path.isEmpty) return null;
  if (path.startsWith('http://') ||
      path.startsWith('https://') ||
      path.startsWith('data:') ||
      path.startsWith('blob:')) {
    return path;
  }

  final apiHost = ApiConstants.baseUrl.replaceFirst(RegExp(r'/api/?$'), '');
  return path.startsWith('/') ? '$apiHost$path' : '$apiHost/$path';
}
