class CategoryModel {
  final int id;
  final String name;
  final String slug;
  final int? parentId;

  CategoryModel({
    required this.id,
    required this.name,
    required this.slug,
    this.parentId,
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: json['id'] as int? ?? 0,
      name: json['name']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
      parentId: json['parent_id'] as int?,
    );
  }
}
