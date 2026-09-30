import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

import '../theme/app_spacing.dart';
import '../theme/theme_context.dart';

class SkeletonBox extends StatelessWidget {
  const SkeletonBox({super.key, this.height = 16, this.width, this.radius = 8});

  final double height;
  final double? width;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final box = Container(
      height: height,
      width: width,
      decoration: BoxDecoration(
        color: cs.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(radius),
      ),
    );
    if (MediaQuery.disableAnimationsOf(context)) return box;
    return Shimmer.fromColors(
      baseColor: cs.surfaceContainerLow,
      highlightColor: cs.surfaceContainerHigh,
      child: box,
    );
  }
}

/// Placeholder list of card-shaped skeleton rows.
class SkeletonList extends StatelessWidget {
  const SkeletonList({super.key, this.count = 5, this.itemHeight = 88});

  final int count;
  final double itemHeight;

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(AppSpacing.space4),
      itemCount: count,
      separatorBuilder: (_, _) => AppSpacing.h12,
      itemBuilder: (_, _) => SkeletonBox(height: itemHeight, radius: 16),
    );
  }
}
