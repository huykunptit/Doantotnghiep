import 'package:flutter/material.dart';

class AppSpacing {
  AppSpacing._();

  static const double space1 = 4.0;
  static const double space2 = 8.0;
  static const double space3 = 12.0;
  static const double space4 = 16.0;
  static const double space5 = 20.0;
  static const double space6 = 24.0;
  static const double space8 = 32.0;
  static const double space10 = 40.0;
  static const double space12 = 48.0;
  static const double space16 = 64.0;
  static const double space20 = 80.0;
  static const double space24 = 96.0;

  static const EdgeInsets p4 = EdgeInsets.all(space1);
  static const EdgeInsets p8 = EdgeInsets.all(space2);
  static const EdgeInsets p12 = EdgeInsets.all(space3);
  static const EdgeInsets p16 = EdgeInsets.all(space4);
  static const EdgeInsets p20 = EdgeInsets.all(space5);
  static const EdgeInsets p24 = EdgeInsets.all(space6);

  static const SizedBox h4 = SizedBox(height: space1);
  static const SizedBox h8 = SizedBox(height: space2);
  static const SizedBox h12 = SizedBox(height: space3);
  static const SizedBox h16 = SizedBox(height: space4);
  static const SizedBox h20 = SizedBox(height: space5);
  static const SizedBox h24 = SizedBox(height: space6);
  static const SizedBox h32 = SizedBox(height: space8);

  static const SizedBox w4 = SizedBox(width: space1);
  static const SizedBox w8 = SizedBox(width: space2);
  static const SizedBox w12 = SizedBox(width: space3);
  static const SizedBox w16 = SizedBox(width: space4);
  static const SizedBox w20 = SizedBox(width: space5);
  static const SizedBox w24 = SizedBox(width: space6);
  static const SizedBox w32 = SizedBox(width: space8);
}

class AppRadius {
  AppRadius._();

  static const double sm = 4.0;
  static const double md = 8.0;
  static const double lg = 12.0;
  static const double xl = 16.0;
  static const double radius2xl = 24.0;

  static BorderRadius get rSm => BorderRadius.circular(sm);
  static BorderRadius get rMd => BorderRadius.circular(md);
  static BorderRadius get rLg => BorderRadius.circular(lg);
  static BorderRadius get rXl => BorderRadius.circular(xl);
  static BorderRadius get r2Xl => BorderRadius.circular(radius2xl);
  static BorderRadius get rFull => BorderRadius.circular(9999.0);
}
