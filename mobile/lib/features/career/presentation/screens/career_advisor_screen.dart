import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../data/models/career_model.dart';
import '../../providers/career_providers.dart';

class CareerAdvisorScreen extends ConsumerStatefulWidget {
  const CareerAdvisorScreen({super.key});

  static const routeName = '/career';

  @override
  ConsumerState<CareerAdvisorScreen> createState() => _CareerAdvisorScreenState();
}

class _CareerAdvisorScreenState extends ConsumerState<CareerAdvisorScreen> {
  final _jobTitleController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  CareerRecommendationModel? _selectedRecommendation;

  @override
  void dispose() {
    _jobTitleController.dispose();
    super.dispose();
  }

  Future<void> _pickAndUploadCV() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'doc', 'docx'],
      );

      if (result != null && result.files.single.path != null) {
        final path = result.files.single.path!;
        final name = result.files.single.name;

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Row(
                children: [
                  SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  ),
                  AppSpacing.w12,
                  Text('Đang tải lên và phân tích CV...'),
                ],
              ),
              duration: Duration(minutes: 1),
            ),
          );
        }

        await ref.read(careerAdvisorNotifierProvider.notifier).uploadCV(path, name);

        if (mounted) {
          ScaffoldMessenger.of(context).clearSnackBars();
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Tải lên CV thành công!'),
              backgroundColor: Colors.green,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).clearSnackBars();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Lỗi tải lên CV: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _getRecommendation() async {
    if (!_formKey.currentState!.validate()) return;

    final jobTitle = _jobTitleController.text.trim();

    try {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Row(
              children: [
                SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                ),
                AppSpacing.w12,
                Text('AI đang xây dựng lộ trình gợi ý...'),
              ],
            ),
            duration: Duration(minutes: 1),
          ),
        );
      }

      final recommendation = await ref
          .read(careerAdvisorNotifierProvider.notifier)
          .requestRecommendation(jobTitle);

      setState(() {
        _selectedRecommendation = recommendation;
        _jobTitleController.clear();
      });

      if (mounted) {
        ScaffoldMessenger.of(context).clearSnackBars();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Đã hoàn thành phân tích lộ trình!'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).clearSnackBars();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Lỗi tạo lộ trình: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final statusAsync = ref.watch(careerAdvisorNotifierProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tư vấn nghề nghiệp AI'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              ref.invalidate(careerAdvisorNotifierProvider);
              setState(() {
                _selectedRecommendation = null;
              });
            },
          ),
        ],
      ),
      body: statusAsync.when(
        loading: () => const Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircularProgressIndicator(),
              AppSpacing.h16,
              Text('Đang kết nối dữ liệu nghề nghiệp...'),
            ],
          ),
        ),
        error: (err, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.error_outline, size: 48, color: Colors.red),
                AppSpacing.h12,
                Text('Lỗi: $err', textAlign: TextAlign.center),
                AppSpacing.h16,
                FilledButton.icon(
                  onPressed: () => ref.invalidate(careerAdvisorNotifierProvider),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
        data: (status) {
          final currentCv = status.cv;
          final recommendations = status.recommendations;

          // Set default recommendation to the latest if not selected yet
          if (_selectedRecommendation == null && recommendations.isNotEmpty) {
            _selectedRecommendation = recommendations.first;
          }

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildIntroHeader(theme),
                AppSpacing.h20,
                _buildCvSection(context, currentCv),
                if (currentCv != null) ...[
                  AppSpacing.h20,
                  _buildRequestSection(theme),
                  if (recommendations.isNotEmpty) ...[
                    AppSpacing.h24,
                    _buildHistorySelector(theme, recommendations),
                    AppSpacing.h16,
                    if (_selectedRecommendation != null)
                      _buildRecommendationDetail(theme, _selectedRecommendation!),
                  ],
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildIntroHeader(ThemeData theme) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.primary50.withValues(alpha: 0.5),
        borderRadius: AppRadius.rLg,
        border: Border.all(color: AppColors.primary100),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              color: AppColors.primary400,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.psychology_outlined,
              color: Colors.white,
              size: 28,
            ),
          ),
          AppSpacing.w16,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Trợ lý nghề nghiệp AI 🧠',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.primary800,
                  ),
                ),
                AppSpacing.h4,
                Text(
                  'Tải lên CV của bạn, điền mục tiêu nghề nghiệp, và để AI phân tích lộ trình học tập, tìm kiếm lỗ hổng kỹ năng, gợi ý khóa học phù hợp.',
                  style: theme.textTheme.bodySmall?.copyWith(height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCvSection(BuildContext context, UserCvModel? cv) {
    final theme = Theme.of(context);

    if (cv == null) {
      return InkWell(
        onTap: _pickAndUploadCV,
        borderRadius: AppRadius.rLg,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 16),
          decoration: BoxDecoration(
            color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
            borderRadius: AppRadius.rLg,
            border: Border.all(
              color: theme.colorScheme.outline.withValues(alpha: 0.5),
              style: BorderStyle.solid,
            ),
          ),
          child: Column(
            children: [
              Icon(
                Icons.cloud_upload_outlined,
                size: 48,
                color: theme.colorScheme.primary,
              ),
              AppSpacing.h16,
              Text(
                'Tải lên CV học viên',
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              AppSpacing.h4,
              Text(
                'Hỗ trợ file PDF, DOC, DOCX tối đa 5MB',
                style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
              AppSpacing.h20,
              FilledButton.icon(
                onPressed: _pickAndUploadCV,
                icon: const Icon(Icons.file_open_outlined),
                label: const Text('Chọn file từ thiết bị'),
              ),
            ],
          ),
        ),
      );
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.description, color: AppColors.primary400, size: 28),
                AppSpacing.w12,
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        cv.fileName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                      ),
                      Text(
                        'Đã tải lên vào ${cv.createdAt.split('T').first}',
                        style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.upload_file_outlined),
                  tooltip: 'Tải CV mới',
                  onPressed: _pickAndUploadCV,
                ),
              ],
            ),
            if (cv.skills.isNotEmpty) ...[
              AppSpacing.h12,
              const Divider(),
              AppSpacing.h8,
              Text(
                'Kỹ năng nhận diện từ CV:',
                style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
              ),
              AppSpacing.h8,
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: cv.skills.map((skill) {
                  return Chip(
                    label: Text(skill, style: const TextStyle(fontSize: 11)),
                    padding: EdgeInsets.zero,
                    visualDensity: VisualDensity.compact,
                    backgroundColor: AppColors.primary50,
                    side: const BorderSide(color: AppColors.primary100),
                  );
                }).toList(),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildRequestSection(ThemeData theme) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Nhận tư vấn vị trí mong muốn 🎯',
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              AppSpacing.h12,
              TextFormField(
                controller: _jobTitleController,
                decoration: const InputDecoration(
                  labelText: 'Ví dụ: Backend Developer, Data Analyst, UI/UX Designer...',
                  hintText: 'Nhập vị trí tuyển dụng mục tiêu',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.search),
                ),
                validator: (val) {
                  if (val == null || val.trim().isEmpty) {
                    return 'Vui lòng nhập vị trí bạn mong muốn tư vấn';
                  }
                  return null;
                },
              ),
              AppSpacing.h16,
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _getRecommendation,
                  icon: const Icon(Icons.insights),
                  label: const Text('Phân tích & tư vấn nghề nghiệp'),
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHistorySelector(ThemeData theme, List<CareerRecommendationModel> recommendations) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          'Kết quả phân tích',
          style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
        ),
        if (recommendations.length > 1)
          DropdownButton<CareerRecommendationModel>(
            value: _selectedRecommendation,
            underline: const SizedBox.shrink(),
            style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.bold, color: theme.colorScheme.primary),
            icon: Icon(Icons.arrow_drop_down, color: theme.colorScheme.primary),
            onChanged: (recommendation) {
              if (recommendation != null) {
                setState(() {
                  _selectedRecommendation = recommendation;
                });
              }
            },
            items: recommendations.map((item) {
              return DropdownMenuItem<CareerRecommendationModel>(
                value: item,
                child: Text('Lần ${recommendations.indexOf(item) + 1} (${item.createdAt.split('T').first})'),
              );
            }).toList(),
          ),
      ],
    );
  }

  Widget _buildRecommendationDetail(ThemeData theme, CareerRecommendationModel rec) {
    final score = rec.matchScore;
    Color scoreColor = Colors.red;
    if (score >= 80) {
      scoreColor = Colors.green;
    } else if (score >= 50) {
      scoreColor = Colors.orange;
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Match Score Card
        Card(
          color: scoreColor.withValues(alpha: 0.08),
          shape: RoundedRectangleBorder(
            borderRadius: AppRadius.rLg,
            side: BorderSide(color: scoreColor.withValues(alpha: 0.3)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Stack(
                  alignment: Alignment.center,
                  children: [
                    SizedBox(
                      width: 64,
                      height: 64,
                      child: CircularProgressIndicator(
                        value: score / 100,
                        strokeWidth: 6,
                        color: scoreColor,
                        backgroundColor: scoreColor.withValues(alpha: 0.2),
                      ),
                    ),
                    Text(
                      '$score%',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                        color: scoreColor,
                      ),
                    ),
                  ],
                ),
                AppSpacing.w20,
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Độ tương thích hồ sơ',
                        style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.bold),
                      ),
                      AppSpacing.h4,
                      Text(
                        rec.expertAnalysis.overview.isNotEmpty 
                            ? rec.expertAnalysis.overview 
                            : rec.aiSummary,
                        style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        AppSpacing.h16,

        // Skill Gaps Section
        if (rec.skillGaps.isNotEmpty) ...[
          Text(
            'Lỗ hổng kỹ năng cần bổ sung ⚠️',
            style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
          ),
          AppSpacing.h8,
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: rec.skillGaps.map((gap) {
              return Chip(
                label: Text(gap, style: TextStyle(color: Colors.red.shade900, fontWeight: FontWeight.bold)),
                backgroundColor: Colors.red.shade50,
                side: BorderSide(color: Colors.red.shade200),
                visualDensity: VisualDensity.compact,
                padding: const EdgeInsets.symmetric(horizontal: 4),
              );
            }).toList(),
          ),
          AppSpacing.h20,
        ],

        // Strengths and Weaknesses
        _buildStrengthsWeaknesses(theme, rec.expertAnalysis),
        AppSpacing.h20,

        // CV Improvements & Additions
        _buildCvImprovementsCard(theme, rec.expertAnalysis),
        AppSpacing.h20,

        // Learning Priorities
        _buildLearningPrioritiesCard(theme, rec.expertAnalysis),
        AppSpacing.h20,

        // Recommended Courses
        if (rec.suggestedCoursesData.isNotEmpty) ...[
          Text(
            'Khóa học được đề xuất riêng cho bạn 📚',
            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
          ),
          AppSpacing.h12,
          ListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: rec.suggestedCoursesData.length,
            itemBuilder: (context, index) {
              final course = rec.suggestedCoursesData[index];
              return _buildCourseRecommendationItem(context, theme, course);
            },
          ),
        ],
      ],
    );
  }

  Widget _buildStrengthsWeaknesses(ThemeData theme, ExpertAnalysisModel analysis) {
    return Column(
      children: [
        if (analysis.strengths.isNotEmpty)
          _buildAnalysisBlock(
            theme: theme,
            title: 'Điểm mạnh hồ sơ',
            items: analysis.strengths,
            icon: Icons.check_circle,
            color: Colors.green,
          ),
        if (analysis.weaknesses.isNotEmpty) ...[
          AppSpacing.h12,
          _buildAnalysisBlock(
            theme: theme,
            title: 'Điểm cần cải thiện',
            items: analysis.weaknesses,
            icon: Icons.info_outline,
            color: Colors.orange.shade800,
          ),
        ],
      ],
    );
  }

  Widget _buildAnalysisBlock({
    required ThemeData theme,
    required String title,
    required List<String> items,
    required IconData icon,
    required Color color,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.04),
        borderRadius: AppRadius.rLg,
        border: Border.all(color: color.withValues(alpha: 0.15)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: color, size: 20),
              AppSpacing.w8,
              Text(
                title,
                style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold, color: color),
              ),
            ],
          ),
          AppSpacing.h12,
          ...items.map((item) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 5),
                      child: Container(
                        width: 5,
                        height: 5,
                        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                      ),
                    ),
                    AppSpacing.w12,
                    Expanded(
                      child: Text(
                        item,
                        style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
                      ),
                    ),
                  ],
                ),
              )),
        ],
      ),
    );
  }

  Widget _buildCvImprovementsCard(ThemeData theme, ExpertAnalysisModel analysis) {
    final list = [...analysis.cvImprovements, ...analysis.cvAdditions];
    if (list.isEmpty) return const SizedBox.shrink();

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: AppRadius.rLg,
        side: BorderSide(color: theme.colorScheme.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.edit_note, color: Colors.blue, size: 24),
                AppSpacing.w8,
                Text(
                  'Hướng dẫn tối ưu hóa CV',
                  style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                ),
              ],
            ),
            AppSpacing.h12,
            ...list.map((item) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.check_box_outlined, color: Colors.blue, size: 18),
                      AppSpacing.w12,
                      Expanded(
                        child: Text(
                          item,
                          style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
                        ),
                      ),
                    ],
                  ),
                )),
          ],
        ),
      ),
    );
  }

  Widget _buildLearningPrioritiesCard(ThemeData theme, ExpertAnalysisModel analysis) {
    if (analysis.learningPriorities.isEmpty) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.2),
        borderRadius: AppRadius.rLg,
        border: Border.all(color: theme.colorScheme.outline.withValues(alpha: 0.1)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.explore_outlined, color: AppColors.primary600, size: 22),
              AppSpacing.w8,
              Text(
                'Lộ trình ưu tiên học tập',
                style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold, color: AppColors.primary800),
              ),
            ],
          ),
          AppSpacing.h12,
          ...analysis.learningPriorities.map((item) {
            final index = analysis.learningPriorities.indexOf(item) + 1;
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(
                    radius: 9,
                    backgroundColor: AppColors.primary400,
                    child: Text(
                      '$index',
                      style: const TextStyle(fontSize: 10, color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                  ),
                  AppSpacing.w12,
                  Expanded(
                    child: Text(
                      item,
                      style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
                    ),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildCourseRecommendationItem(
    BuildContext context,
    ThemeData theme,
    CareerRecommendationCourseModel course,
  ) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/courses/${course.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Course Thumbnail Placeholder/Image
                  Container(
                    width: 72,
                    height: 72,
                    decoration: BoxDecoration(
                      color: theme.colorScheme.surfaceContainerHighest,
                      borderRadius: AppRadius.rMd,
                    ),
                    alignment: Alignment.center,
                    child: course.thumbnail != null
                        ? ClipRRect(
                            borderRadius: AppRadius.rMd,
                            child: Image.network(
                              course.thumbnail!,
                              fit: BoxFit.cover,
                              width: 72,
                              height: 72,
                              errorBuilder: (_, _, _) =>
                                  const Icon(Icons.school, size: 28, color: Colors.grey),
                            ),
                          )
                        : const Icon(Icons.school, size: 28, color: Colors.grey),
                  ),
                  AppSpacing.w12,
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          course.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                        ),
                        AppSpacing.h4,
                        Row(
                          children: [
                            Icon(Icons.star, color: Colors.amber.shade700, size: 14),
                            AppSpacing.w4,
                            Text(
                              course.avgRating.toStringAsFixed(1),
                              style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.bold),
                            ),
                            AppSpacing.w12,
                            Text(
                              course.price > 0 ? '${course.price}đ' : 'Miễn phí',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: theme.colorScheme.primary,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            if (course.recommendationReason != null && course.recommendationReason!.isNotEmpty) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                color: AppColors.primary50.withValues(alpha: 0.3),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.auto_awesome, color: AppColors.primary400, size: 14),
                    AppSpacing.w8,
                    Expanded(
                      child: Text(
                        course.recommendationReason!,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.primary800,
                          fontStyle: FontStyle.italic,
                          fontSize: 11,
                          height: 1.3,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
