import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/career_path_model.dart';
import '../data/repositories/path_repository.dart';

part 'path_detail_provider.g.dart';

@riverpod
Future<CareerPathDetail> pathDetail(PathDetailRef ref, String slug) =>
    ref.read(pathRepositoryProvider).getPath(slug);
