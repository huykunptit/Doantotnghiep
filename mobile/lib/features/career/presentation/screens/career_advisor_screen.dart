import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../data/models/career_model.dart';
import '../../providers/career_providers.dart';
import '../../../../core/error/friendly_error.dart';

class CareerAdvisorScreen extends ConsumerStatefulWidget {
  const CareerAdvisorScreen({super.key});

  static const routeName = '/career';

  @override
  ConsumerState<CareerAdvisorScreen> createState() => _CareerAdvisorScreenState();
}

class _CareerAdvisorScreenState extends ConsumerState<CareerAdvisorScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final _jobTitleController = TextEditingController();
  final _salaryController = TextEditingController(text: '8000000');
  final _recommendFormKey = GlobalKey<FormState>();

  final _fullNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _headlineController = TextEditingController();
  final _summaryController = TextEditingController();
  final _skillsController = TextEditingController();
  final _targetRoleController = TextEditingController();
  final _formSalaryController = TextEditingController(text: '8000000');
  final _cvFormKey = GlobalKey<FormState>();

  CareerRecommendationModel? _selectedRecommendation;
  CareerEvaluationModel? _evaluation;
  List<CareerRecommendationCourseModel> _evalCourses = [];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _jobTitleController.dispose();
    _salaryController.dispose();
    _fullNameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _headlineController.dispose();
    _summaryController.dispose();
    _skillsController.dispose();
    _targetRoleController.dispose();
    _formSalaryController.dispose();
    super.dispose();
  }

  void _hydrateFormFromCv(UserCvModel? cv) {
    if (cv == null) return;
    if (_targetRoleController.text.isEmpty && (cv.targetRole?.isNotEmpty ?? false)) {
      _targetRoleController.text = cv.targetRole!;
      _jobTitleController.text = cv.targetRole!;
    }
    if (cv.expectedSalary != null) {
      _formSalaryController.text = '${cv.expectedSalary}';
      _salaryController.text = '${cv.expectedSalary}';
    }
    if (_skillsController.text.isEmpty && cv.skills.isNotEmpty) {
      _skillsController.text = cv.skills.join(', ');
    }
    _evaluation ??= cv.evaluation;
  }

  Future<void> _pickAndUploadCV() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'doc', 'docx'],
      );
      if (result == null || result.files.single.path == null) return;

      setState(() => _busy = true);
      _showBusy('Đang tải lên và phân tích CV...');
      await ref
          .read(careerAdvisorNotifierProvider.notifier)
          .uploadCV(result.files.single.path!, result.files.single.name);
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tải lên CV thành công!'), backgroundColor: Colors.green),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Lỗi tải lên CV: ${friendlyErrorMessage(e)}'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _saveCvForm() async {
    if (!_cvFormKey.currentState!.validate()) return;
    setState(() => _busy = true);
    _showBusy('Đang lưu form CV...');
    try {
      final skills = _skillsController.text
          .split(',')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty)
          .toList();
      final salary = int.tryParse(_formSalaryController.text.trim());
      final result = await ref.read(careerAdvisorNotifierProvider.notifier).saveCvForm({
        'full_name': _fullNameController.text.trim(),
        'email': _emailController.text.trim().isEmpty ? null : _emailController.text.trim(),
        'phone': _phoneController.text.trim().isEmpty ? null : _phoneController.text.trim(),
        'headline': _headlineController.text.trim().isEmpty ? null : _headlineController.text.trim(),
        'summary': _summaryController.text.trim().isEmpty ? null : _summaryController.text.trim(),
        'skills': skills,
        'target_role':
            _targetRoleController.text.trim().isEmpty ? null : _targetRoleController.text.trim(),
        'expected_salary': salary,
      });
      setState(() {
        _evaluation = result.evaluation;
        _evalCourses = result.suggestedCourses;
        if ((_targetRoleController.text).isNotEmpty) {
          _jobTitleController.text = _targetRoleController.text.trim();
        }
        if (salary != null) _salaryController.text = '$salary';
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã lưu CV từ form'), backgroundColor: Colors.green),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Lỗi lưu form: ${friendlyErrorMessage(e)}'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _evaluateCv() async {
    setState(() => _busy = true);
    _showBusy('AI đang đánh giá CV...');
    try {
      final salary = int.tryParse(_salaryController.text.trim()) ??
          int.tryParse(_formSalaryController.text.trim());
      final role = _jobTitleController.text.trim().isNotEmpty
          ? _jobTitleController.text.trim()
          : _targetRoleController.text.trim();
      final result = await ref.read(careerAdvisorNotifierProvider.notifier).evaluate(
            targetRole: role.isEmpty ? null : role,
            expectedSalary: salary,
          );
      setState(() {
        _evaluation = result.evaluation;
        _evalCourses = result.suggestedCourses;
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã đánh giá CV'), backgroundColor: Colors.green),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Lỗi đánh giá: ${friendlyErrorMessage(e)}'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _getRecommendation() async {
    if (!_recommendFormKey.currentState!.validate()) return;
    setState(() => _busy = true);
    _showBusy('AI đang xây dựng lộ trình gợi ý...');
    try {
      final salary = int.tryParse(_salaryController.text.trim());
      final recommendation = await ref
          .read(careerAdvisorNotifierProvider.notifier)
          .requestRecommendation(
            _jobTitleController.text.trim(),
            expectedSalary: salary,
          );
      setState(() => _selectedRecommendation = recommendation);
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã hoàn thành phân tích lộ trình!'), backgroundColor: Colors.green),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Lỗi tạo lộ trình: ${friendlyErrorMessage(e)}'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showBusy(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
            ),
            AppSpacing.w12,
            Expanded(child: Text(message)),
          ],
        ),
        duration: const Duration(minutes: 1),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final statusAsync = ref.watch(careerAdvisorNotifierProvider);
    final theme = Theme.of(context);

    return Scaffold(
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
        title: const Text('AI Career'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _busy
                ? null
                : () {
                    ref.invalidate(careerAdvisorNotifierProvider);
                    setState(() {
                      _selectedRecommendation = null;
                      _evaluation = null;
                      _evalCourses = [];
                    });
                  },
          ),
        ],
      ),
      body: statusAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Lỗi: $err', textAlign: TextAlign.center),
                AppSpacing.h16,
                FilledButton(
                  onPressed: () => ref.invalidate(careerAdvisorNotifierProvider),
                  child: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
        data: (status) {
          final currentCv = status.cv;
          final recommendations = status.recommendations;
          _hydrateFormFromCv(currentCv);
          if (_selectedRecommendation == null && recommendations.isNotEmpty) {
            _selectedRecommendation = recommendations.first;
          }

          return ListView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: EdgeInsets.fromLTRB(
              16,
              16,
              16,
              16 + MediaQuery.of(context).viewInsets.bottom,
            ),
            children: [
              _buildIntroHeader(theme),
              AppSpacing.h16,
              TabBar(
                controller: _tabController,
                tabs: const [
                  Tab(text: 'Upload CV'),
                  Tab(text: 'Form CV'),
                ],
              ),
              AppSpacing.h12,
              AnimatedBuilder(
                animation: _tabController,
                builder: (context, _) {
                  if (_tabController.index == 0) {
                    return _buildCvUploadSection(context, currentCv);
                  }
                  return _buildCvFormSection(theme);
                },
              ),
              if (currentCv != null || _evaluation != null) ...[
                AppSpacing.h20,
                _buildTargetSection(theme),
                AppSpacing.h12,
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _busy ? null : _evaluateCv,
                        icon: const Icon(Icons.fact_check_outlined),
                        label: const Text('Đánh giá CV'),
                      ),
                    ),
                    AppSpacing.w8,
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: _busy ? null : _getRecommendation,
                        icon: const Icon(Icons.insights),
                        label: const Text('Gợi ý khóa'),
                      ),
                    ),
                  ],
                ),
              ],
              if (_evaluation != null) ...[
                AppSpacing.h20,
                _buildEvaluationCard(theme, _evaluation!),
              ],
              if (_evalCourses.isNotEmpty) ...[
                AppSpacing.h16,
                Text(
                  'Khóa học từ đánh giá',
                  style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                ),
                AppSpacing.h8,
                ..._evalCourses.map((c) => _buildCourseRecommendationItem(context, theme, c)),
              ],
              if (currentCv != null && recommendations.isNotEmpty) ...[
                AppSpacing.h24,
                _buildHistorySelector(theme, recommendations),
                AppSpacing.h16,
                if (_selectedRecommendation != null)
                  _buildRecommendationDetail(theme, _selectedRecommendation!),
              ],
            ],
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
            child: const Icon(Icons.work_outline, color: Colors.white, size: 28),
          ),
          AppSpacing.w16,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'AI Career',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.primary800,
                  ),
                ),
                AppSpacing.h4,
                Text(
                  'Upload hoặc điền form CV → đánh giá → chọn vị trí & mức lương → nhận khóa học gợi ý.',
                  style: theme.textTheme.bodySmall?.copyWith(height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCvUploadSection(BuildContext context, UserCvModel? cv) {
    final theme = Theme.of(context);
    if (cv == null || cv.source == 'form') {
      return InkWell(
        onTap: _busy ? null : _pickAndUploadCV,
        borderRadius: AppRadius.rLg,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 16),
          decoration: BoxDecoration(
            color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
            borderRadius: AppRadius.rLg,
            border: Border.all(color: theme.colorScheme.outline.withValues(alpha: 0.5)),
          ),
          child: Column(
            children: [
              Icon(Icons.cloud_upload_outlined, size: 48, color: theme.colorScheme.primary),
              AppSpacing.h12,
              Text('Tải lên CV (PDF/DOC/DOCX)', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
              AppSpacing.h12,
              FilledButton.icon(
                onPressed: _busy ? null : _pickAndUploadCV,
                icon: const Icon(Icons.file_open_outlined),
                label: const Text('Chọn file'),
              ),
            ],
          ),
        ),
      );
    }

    return Card(
      child: ListTile(
        leading: const Icon(Icons.description, color: AppColors.primary400),
        title: Text(cv.fileName, maxLines: 1, overflow: TextOverflow.ellipsis),
        subtitle: Text('Đã tải lên ${cv.createdAt.split('T').first}'),
        trailing: IconButton(
          icon: const Icon(Icons.upload_file_outlined),
          onPressed: _busy ? null : _pickAndUploadCV,
        ),
      ),
    );
  }

  Widget _buildCvFormSection(ThemeData theme) {
    return Form(
      key: _cvFormKey,
      child: Column(
        children: [
          TextFormField(
            controller: _fullNameController,
            decoration: const InputDecoration(labelText: 'Họ tên *', border: OutlineInputBorder()),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Bắt buộc' : null,
          ),
          AppSpacing.h12,
          TextFormField(
            controller: _emailController,
            decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder()),
          ),
          AppSpacing.h12,
          TextFormField(
            controller: _phoneController,
            decoration: const InputDecoration(labelText: 'SĐT', border: OutlineInputBorder()),
          ),
          AppSpacing.h12,
          TextFormField(
            controller: _headlineController,
            decoration: const InputDecoration(labelText: 'Headline / Vị trí', border: OutlineInputBorder()),
          ),
          AppSpacing.h12,
          TextFormField(
            controller: _summaryController,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Tóm tắt', border: OutlineInputBorder()),
          ),
          AppSpacing.h12,
          TextFormField(
            controller: _skillsController,
            decoration: const InputDecoration(
              labelText: 'Kỹ năng (cách nhau bởi dấu phẩy)',
              border: OutlineInputBorder(),
            ),
          ),
          AppSpacing.h12,
          TextFormField(
            controller: _targetRoleController,
            decoration: const InputDecoration(labelText: 'Vị trí mục tiêu', border: OutlineInputBorder()),
          ),
          AppSpacing.h12,
          TextFormField(
            controller: _formSalaryController,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: const InputDecoration(
              labelText: 'Mức lương mong muốn (VND)',
              border: OutlineInputBorder(),
            ),
          ),
          AppSpacing.h16,
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _busy ? null : _saveCvForm,
              icon: const Icon(Icons.save_outlined),
              label: const Text('Lưu form & đánh giá'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTargetSection(ThemeData theme) {
    return Form(
      key: _recommendFormKey,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Vị trí & mức lương mục tiêu', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
              AppSpacing.h12,
              TextFormField(
                controller: _jobTitleController,
                decoration: const InputDecoration(
                  labelText: 'Ví dụ: Backend Developer',
                  border: OutlineInputBorder(),
                ),
                validator: (val) {
                  if (val == null || val.trim().isEmpty) {
                    return 'Nhập vị trí mục tiêu';
                  }
                  return null;
                },
              ),
              AppSpacing.h12,
              TextFormField(
                controller: _salaryController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: const InputDecoration(
                  labelText: 'Mức lương mong muốn (VND)',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEvaluationCard(ThemeData theme, CareerEvaluationModel evaluation) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text('${evaluation.score}%', style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w900, color: AppColors.primary600)),
                AppSpacing.w12,
                Expanded(
                  child: Text(
                    evaluation.summary,
                    style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
                  ),
                ),
              ],
            ),
            if (evaluation.salaryNote != null) ...[
              AppSpacing.h8,
              Text(evaluation.salaryNote!, style: theme.textTheme.bodySmall),
            ],
            if (evaluation.checks.isNotEmpty) ...[
              AppSpacing.h12,
              ...evaluation.checks.map(
                (c) => ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    c.ok ? Icons.check_circle : Icons.cancel_outlined,
                    color: c.ok ? Colors.green : Colors.orange,
                  ),
                  title: Text(c.label),
                ),
              ),
            ],
            if (evaluation.fixes.isNotEmpty) ...[
              AppSpacing.h8,
              Text('Cần cải thiện', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
              ...evaluation.fixes.map((f) => Text('• $f')),
            ],
            if (evaluation.warnings.isNotEmpty) ...[
              AppSpacing.h8,
              Text('Cảnh báo', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
              ...evaluation.warnings.map((w) => Text('• $w')),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildHistorySelector(ThemeData theme, List<CareerRecommendationModel> recommendations) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text('Kết quả phân tích', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
        if (recommendations.length > 1)
          DropdownButton<CareerRecommendationModel>(
            value: _selectedRecommendation,
            underline: const SizedBox.shrink(),
            onChanged: (recommendation) {
              if (recommendation != null) {
                setState(() => _selectedRecommendation = recommendation);
              }
            },
            items: recommendations.map((item) {
              return DropdownMenuItem(
                value: item,
                child: Text('Lần ${recommendations.indexOf(item) + 1}'),
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
        Card(
          color: scoreColor.withValues(alpha: 0.08),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Text('$score%', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: scoreColor)),
                AppSpacing.w16,
                Expanded(
                  child: Text(
                    rec.expertAnalysis.overview.isNotEmpty ? rec.expertAnalysis.overview : rec.aiSummary,
                  ),
                ),
              ],
            ),
          ),
        ),
        if (rec.skillGaps.isNotEmpty) ...[
          AppSpacing.h12,
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: rec.skillGaps
                .map((gap) => Chip(label: Text(gap), backgroundColor: Colors.red.shade50))
                .toList(),
          ),
        ],
        if (rec.suggestedCoursesData.isNotEmpty) ...[
          AppSpacing.h16,
          Text('Khóa học đề xuất', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          AppSpacing.h8,
          ...rec.suggestedCoursesData.map((c) => _buildCourseRecommendationItem(context, theme, c)),
        ],
      ],
    );
  }

  Widget _buildCourseRecommendationItem(
    BuildContext context,
    ThemeData theme,
    CareerRecommendationCourseModel course,
  ) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        onTap: () => context.push('/courses/${course.id}'),
        leading: const Icon(Icons.school_outlined),
        title: Text(course.title, maxLines: 2, overflow: TextOverflow.ellipsis),
        subtitle: Text(course.recommendationReason ?? (course.price > 0 ? '${course.price}đ' : 'Miễn phí')),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}
