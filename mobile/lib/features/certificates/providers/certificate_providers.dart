import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/certificate_model.dart';
import '../data/repositories/certificate_repository.dart';

part 'certificate_providers.g.dart';

@riverpod
Future<List<UserCertificateModel>> myCertificates(MyCertificatesRef ref) {
  return ref.read(certificateRepositoryProvider).getMyCertificates();
}
