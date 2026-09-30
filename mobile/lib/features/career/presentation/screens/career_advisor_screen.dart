import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../data/models/career_model.dart';
import '../../providers/career_providers.dart';
import '../../../../core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import '../widgets/career_panels.dart';
import '../widgets/career_results.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class CareerAdvisorScreen extends ConsumerStatefulWidget {
  const CareerAdvisorScreen({super.key});

  static const routeName = '/career';

  @override
  ConsumerState<CareerAdvisorScreen> createState() =>
      _CareerAdvisorScreenState();
}

class _CareerAdvisorScreenState extends ConsumerState<CareerAdvisorScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final _jobTitleController = TextEditingController();
  final _salaryController = TextEditingController(text: '8000000');
  final _cv = CvFormControllers();
  final _recommendFormKey = GlobalKey<FormState>();

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
    _cv.dispose();
    _jobTitleController.dispose();
    _salaryController.dispose();
    super.dispose();
  }

  void _hydrateFormFromCv(UserCvModel? cv) {
    if (cv == null) return;
    if (_cv.targetRole.text.isEmpty && (cv.targetRole?.isNotEmpty ?? false)) {
      _cv.targetRole.text = cv.targetRole!;
      _jobTitleController.text = cv.targetRole!;
    }
    if (cv.expectedSalary != null) {
      _cv.salary.text = '${cv.expectedSalary}';
      _salaryController.text = '${cv.expectedSalary}';
    }
    if (_cv.skills.text.isEmpty && cv.skills.isNotEmpty) {
      _cv.skills.text = cv.skills.join(', ');
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
      _toast('Tải lên CV thành công!');
    } catch (e) {
      _toast('Lỗi tải lên CV: ${friendlyErrorMessage(e)}', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _saveCvForm() async {
    if (!_cvFormKey.currentState!.validate()) return;
    setState(() => _busy = true);
    _showBusy('Đang lưu form CV...');
    try {
      final skills = _cv.skills.text
          .split(',')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty)
          .toList();
      final salary = int.tryParse(_cv.salary.text.trim());
      final result = await ref
          .read(careerAdvisorNotifierProvider.notifier)
          .saveCvForm({
            'full_name': _cv.fullName.text.trim(),
            'email': _cv.email.text.trim().isEmpty
                ? null
                : _cv.email.text.trim(),
            'phone': _cv.phone.text.trim().isEmpty
                ? null
                : _cv.phone.text.trim(),
            'headline': _cv.headline.text.trim().isEmpty
                ? null
                : _cv.headline.text.trim(),
            'summary': _cv.summary.text.trim().isEmpty
                ? null
                : _cv.summary.text.trim(),
            'skills': skills,
            'target_role': _cv.targetRole.text.trim().isEmpty
                ? null
                : _cv.targetRole.text.trim(),
            'expected_salary': salary,
          });
      setState(() {
        _evaluation = result.evaluation;
        _evalCourses = result.suggestedCourses;
        if ((_cv.targetRole.text).isNotEmpty) {
          _jobTitleController.text = _cv.targetRole.text.trim();
        }
        if (salary != null) _salaryController.text = '$salary';
      });
      _toast('Đã lưu CV từ form');
    } catch (e) {
      _toast('Lỗi lưu form: ${friendlyErrorMessage(e)}', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _evaluateCv() async {
    setState(() => _busy = true);
    _showBusy('AI đang đánh giá CV...');
    try {
      final salary =
          int.tryParse(_salaryController.text.trim()) ??
          int.tryParse(_cv.salary.text.trim());
      final role = _jobTitleController.text.trim().isNotEmpty
          ? _jobTitleController.text.trim()
          : _cv.targetRole.text.trim();
      final result = await ref
          .read(careerAdvisorNotifierProvider.notifier)
          .evaluate(
            targetRole: role.isEmpty ? null : role,
            expectedSalary: salary,
          );
      setState(() {
        _evaluation = result.evaluation;
        _evalCourses = result.suggestedCourses;
      });
      _toast('Đã đánh giá CV');
    } catch (e) {
      _toast('Lỗi đánh giá: ${friendlyErrorMessage(e)}', error: true);
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
      _toast('Đã hoàn thành phân tích lộ trình!');
    } catch (e) {
      _toast('Lỗi tạo lộ trình: ${friendlyErrorMessage(e)}', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _toast(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..clearSnackBars()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: error ? context.sem.danger : context.sem.success,
        ),
      );
  }

  void _showBusy(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: context.cs.surface,
              ),
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

    return Scaffold(
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
        title: const Text('AI Career'),
        actions: [
          IconButton(
            tooltip: 'Làm mới',
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
        error: (err, _) => ErrorStateWidget(
          error: err,
          onRetry: () => ref.invalidate(careerAdvisorNotifierProvider),
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
              const CareerIntroHeader(),
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
                builder: (context, _) => _tabController.index == 0
                    ? CvUploadPanel(
                        cv: currentCv,
                        busy: _busy,
                        onPick: _pickAndUploadCV,
                      )
                    : CvFormPanel(
                        formKey: _cvFormKey,
                        controllers: _cv,
                        busy: _busy,
                        onSave: _saveCvForm,
                      ),
              ),
              if (currentCv != null || _evaluation != null) ...[
                AppSpacing.h20,
                CareerTargetPanel(
                  formKey: _recommendFormKey,
                  jobTitle: _jobTitleController,
                  salary: _salaryController,
                ),
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
                CareerEvaluationCard(evaluation: _evaluation!),
              ],
              if (_evalCourses.isNotEmpty) ...[
                AppSpacing.h16,
                Text('Khóa học từ đánh giá', style: context.tt.titleMedium),
                AppSpacing.h8,
                for (final c in _evalCourses) CareerCourseTile(course: c),
              ],
              if (currentCv != null && recommendations.isNotEmpty) ...[
                AppSpacing.h24,
                RecommendationHistoryHeader(
                  recommendations: recommendations,
                  selected: _selectedRecommendation,
                  onChanged: (r) => setState(() => _selectedRecommendation = r),
                ),
                AppSpacing.h16,
                if (_selectedRecommendation != null)
                  RecommendationDetail(
                    recommendation: _selectedRecommendation!,
                  ),
              ],
            ],
          );
        },
      ),
    );
  }
}
