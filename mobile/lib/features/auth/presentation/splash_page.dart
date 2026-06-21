import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';

class SplashPage extends ConsumerWidget {
  const SplashPage({super.key});

  static const routeName = '/';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.listen<AsyncValue<dynamic>>(authNotifierProvider, (_, next) {
      if (!next.isLoading) {
        final user = next.valueOrNull;
        if (user != null) {
          context.go('/home');
        } else {
          context.go('/login');
        }
      }
    });

    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            FlutterLogo(size: 72),
            SizedBox(height: 24),
            CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}
