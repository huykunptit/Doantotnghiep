import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/career_path_model.dart';
import '../data/repositories/path_repository.dart';

part 'paths_catalog_provider.g.dart';

@riverpod
Future<List<CareerPathListItem>> pathsCatalog(
  Ref ref, {
  String? search,
}) async {
  final result = await ref.read(pathRepositoryProvider).listPaths(search: search);
  return result.items;
}

@riverpod
Future<int> pathsCatalogTotal(PathsCatalogTotalRef ref, {String? search}) async {
  final result = await ref.read(pathRepositoryProvider).listPaths(search: search);
  return result.total;
}
