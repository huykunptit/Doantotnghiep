import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../auth/data/models/user_model.dart';
import '../../auth/providers/auth_provider.dart';
import '../data/repositories/profile_repository.dart';

part 'profile_provider.g.dart';

@riverpod
class ProfileNotifier extends _$ProfileNotifier {
  @override
  Future<UserModel?> build() async {
    return ref.watch(authNotifierProvider).valueOrNull;
  }

  Future<void> updateProfile({required String name, String? phone}) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(profileRepositoryProvider).updateProfile(name: name, phone: phone),
    );
    if (state.hasValue) {
      await ref.read(authNotifierProvider.notifier).updateProfile(name: name, phone: phone);
    }
  }

  Future<String?> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    try {
      await ref.read(profileRepositoryProvider).changePassword(
            currentPassword: currentPassword,
            newPassword: newPassword,
          );
      return null;
    } catch (e) {
      return e.toString();
    }
  }
}
