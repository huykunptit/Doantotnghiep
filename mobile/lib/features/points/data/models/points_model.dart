class PointSummaryModel {
  final int balance;
  final int streakDays;
  final String? lastLoginDate;
  final List<PointTransactionModel> recentTransactions;

  PointSummaryModel({
    required this.balance,
    required this.streakDays,
    this.lastLoginDate,
    required this.recentTransactions,
  });

  factory PointSummaryModel.fromJson(Map<String, dynamic> json) {
    return PointSummaryModel(
      balance: json['balance'] as int? ?? 0,
      streakDays: json['streak_days'] as int? ?? 0,
      lastLoginDate: json['last_login_date']?.toString(),
      recentTransactions: (json['recent_transactions'] as List<dynamic>?)
              ?.map((e) => PointTransactionModel.fromJson(
                  e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class PointTransactionModel {
  final int id;
  final String type; // earn, redeem
  final String action;
  final int amount;
  final String description;
  final String createdAt;

  PointTransactionModel({
    required this.id,
    required this.type,
    required this.action,
    required this.amount,
    required this.description,
    required this.createdAt,
  });

  factory PointTransactionModel.fromJson(Map<String, dynamic> json) {
    return PointTransactionModel(
      id: json['id'] as int? ?? 0,
      type: json['type']?.toString() ?? 'earn',
      action: json['action']?.toString() ?? '',
      amount: json['amount'] as int? ?? 0,
      description: json['description']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

class VoucherModel {
  final int id;
  final String name;
  final String? description;
  final String type;
  final int? discountValue;
  final int pointsCost;
  final int? totalQuantity;
  final int redeemedCount;
  final String? image;
  final String? expiresAt;
  final VoucherCourseRef? course;

  VoucherModel({
    required this.id,
    required this.name,
    this.description,
    required this.type,
    this.discountValue,
    required this.pointsCost,
    this.totalQuantity,
    required this.redeemedCount,
    this.image,
    this.expiresAt,
    this.course,
  });

  factory VoucherModel.fromJson(Map<String, dynamic> json) {
    return VoucherModel(
      id: json['id'] as int? ?? 0,
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString(),
      type: json['type']?.toString() ?? 'discount_percent',
      discountValue: json['discount_value'] as int?,
      pointsCost: json['points_cost'] as int? ?? 0,
      totalQuantity: json['total_quantity'] as int?,
      redeemedCount: json['redeemed_count'] as int? ?? 0,
      image: json['image']?.toString(),
      expiresAt: json['expires_at']?.toString(),
      course: json['course'] != null
          ? VoucherCourseRef.fromJson(json['course'] as Map<String, dynamic>)
          : null,
    );
  }

  int? get remaining {
    if (totalQuantity == null) return null;
    return totalQuantity! - redeemedCount;
  }

  String get typeLabel {
    switch (type) {
      case 'discount_percent':
        return 'Giảm ${discountValue ?? 0}%';
      case 'discount_fixed':
        return 'Giảm ${_fmtAmount(discountValue ?? 0)}';
      case 'free_course':
        return 'Khóa học miễn phí';
      case 'physical_gift':
        return 'Quà tặng hiện vật';
      case 'ai_quota':
        return 'AI Quota thêm';
      default:
        return type;
    }
  }

  String _fmtAmount(int amount) {
    if (amount >= 1000000) return '${(amount / 1000000).toStringAsFixed(0)}M₫';
    if (amount >= 1000) return '${(amount / 1000).toStringAsFixed(0)}K₫';
    return '${amount}₫';
  }
}

class VoucherCourseRef {
  final int id;
  final String title;
  final String? thumbnail;

  VoucherCourseRef({required this.id, required this.title, this.thumbnail});

  factory VoucherCourseRef.fromJson(Map<String, dynamic> json) {
    return VoucherCourseRef(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      thumbnail: json['thumbnail']?.toString(),
    );
  }
}

class UserVoucherModel {
  final int id;
  final String code;
  final String status;
  final int pointsSpent;
  final String? usedAt;
  final String? expiresAt;
  final String createdAt;
  final VoucherModel? voucher;

  UserVoucherModel({
    required this.id,
    required this.code,
    required this.status,
    required this.pointsSpent,
    this.usedAt,
    this.expiresAt,
    required this.createdAt,
    this.voucher,
  });

  factory UserVoucherModel.fromJson(Map<String, dynamic> json) {
    return UserVoucherModel(
      id: json['id'] as int? ?? 0,
      code: json['code']?.toString() ?? '',
      status: json['status']?.toString() ?? 'unused',
      pointsSpent: json['points_spent'] as int? ?? 0,
      usedAt: json['used_at']?.toString(),
      expiresAt: json['expires_at']?.toString(),
      createdAt: json['created_at']?.toString() ?? '',
      voucher: json['voucher'] != null
          ? VoucherModel.fromJson(json['voucher'] as Map<String, dynamic>)
          : null,
    );
  }
}

class DailyLoginResult {
  final bool rewarded;
  final String message;
  final int? earned;
  final int balance;
  final int streak;

  DailyLoginResult({
    required this.rewarded,
    required this.message,
    this.earned,
    required this.balance,
    required this.streak,
  });

  factory DailyLoginResult.fromJson(Map<String, dynamic> json) {
    return DailyLoginResult(
      rewarded: json['rewarded'] as bool? ?? false,
      message: json['message']?.toString() ?? '',
      earned: json['earned'] as int?,
      balance: json['balance'] as int? ?? 0,
      streak: json['streak'] as int? ?? 0,
    );
  }
}
