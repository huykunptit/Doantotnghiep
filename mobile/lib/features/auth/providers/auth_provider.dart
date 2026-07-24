import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/user_model.dart';
import '../data/repositories/auth_repository.dart';
import '../../../core/storage/secure_storage.dart';

part 'auth_provider.g.dart';

@riverpod
class AuthNotifier extends _$AuthNotifier {
  @override
  Future<UserModel?> build() async {
    final storage = ref.read(secureStorageProvider);
    // Bound storage/API so splash never hangs forever (esp. web + dead backend).
    final token = await storage.getToken().timeout(
      const Duration(seconds: 5),
      onTimeout: () => null,
    );
    if (token == null) return null;
    try {
      return await ref.read(authRepositoryProvider).getMe().timeout(
        const Duration(seconds: 12),
      );
    } catch (_) {
      await storage.deleteToken();
      return null;
    }
  }

  Future<void> login({required String email, required String password}) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(authRepositoryProvider).login(email: email, password: password),
    );
  }

  Future<void> logout() async {
    await ref.read(authRepositoryProvider).logout();
    state = const AsyncData(null);
  }

  Future<void> updateProfile({required String name, String? phone}) async {
    final updated = await ref
        .read(authRepositoryProvider)
        .updateProfile(name: name, phone: phone);
    state = AsyncData(updated);
  }

  Future<String> register({
    required String name,
    required String email,
    required String password,
  }) {
    return ref.read(authRepositoryProvider).register(
          name: name,
          email: email,
          password: password,
        );
  }

  Future<String> forgotPassword({required String email}) {
    return ref.read(authRepositoryProvider).forgotPassword(email: email);
  }

  Future<String> resendVerificationEmail({required String email}) {
    return ref.read(authRepositoryProvider).resendVerificationEmail(email: email);
  }

  Future<String> getGoogleLoginUrl() {
    return ref.read(authRepositoryProvider).getGoogleLoginUrl();
  }

  Future<void> loginWithGoogle(String queryString) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(authRepositoryProvider).verifyGoogleCallback(queryString),
    );
  }
}
