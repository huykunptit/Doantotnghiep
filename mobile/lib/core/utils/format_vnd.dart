import 'package:intl/intl.dart';

/// Vietnamese đồng with thousand separators, e.g. `300.000đ`.
String formatVnd(num amount) {
  if (amount <= 0) return 'Miễn phí';
  final formatted = NumberFormat.decimalPattern('vi_VN').format(amount.round());
  return '$formattedđ';
}

String formatLessonDuration(int? seconds) {
  if (seconds == null || seconds <= 0) return '';
  final minutes = seconds ~/ 60;
  if (minutes < 60) return '$minutes phút';
  final hours = minutes ~/ 60;
  final remain = minutes % 60;
  if (remain == 0) return hours == 1 ? '1 giờ' : '$hours giờ';
  return '$hours giờ $remain phút';
}
