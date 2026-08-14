import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../auth/providers/auth_provider.dart';
import '../../data/models/ai_models.dart';
import '../../data/repositories/ai_repository.dart';

class AiChatScreen extends ConsumerStatefulWidget {
  const AiChatScreen({super.key, this.courseId});

  final int? courseId;

  static const routeName = '/ai-chat';

  @override
  ConsumerState<AiChatScreen> createState() => _AiChatScreenState();
}

class _AiChatScreenState extends ConsumerState<AiChatScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final _messages = <AiChatMessage>[];
  bool _loading = false;
  bool _restored = false;

  AiChatMessage get _welcome => AiChatMessage(
        role: 'assistant',
        text: widget.courseId != null
            ? 'Xin chào! Tôi đang hỗ trợ bạn trong khóa học này — hỏi về bài học, quiz hoặc mẹo học tập nhé.'
            : 'Xin chào! Tôi có thể gợi ý khóa học, lộ trình học hoặc giải đáp thắc mắc về hệ thống.',
      );

  int? get _userId => ref.read(authNotifierProvider).valueOrNull?.id;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _restore());
  }

  Future<void> _restore() async {
    final saved = await ref.read(aiChatHistoryStoreProvider).load(
          userId: _userId,
          courseId: widget.courseId,
        );
    if (!mounted) return;
    setState(() {
      _messages
        ..clear()
        ..addAll(saved.isNotEmpty ? saved : [_welcome]);
      _restored = true;
    });
    if (saved.length > 1) _scrollToEnd();
  }

  Future<void> _persist() async {
    if (!_restored) return;
    await ref.read(aiChatHistoryStoreProvider).save(
          messages: _messages,
          userId: _userId,
          courseId: widget.courseId,
        );
  }

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  List<String> get _quick => widget.courseId != null
      ? const ['Tóm tắt bài này', 'Gợi ý ôn tập', 'Mẹo làm quiz']
      : const ['Tìm khóa phù hợp', 'Lộ trình học gợi ý', 'Cách dùng hệ thống'];

  Future<void> _send([String? preset]) async {
    final text = (preset ?? _input.text).trim();
    if (text.isEmpty || _loading) return;

    setState(() {
      _messages.add(AiChatMessage(role: 'user', text: text));
      _input.clear();
      _loading = true;
    });
    _scrollToEnd();
    await _persist();

    try {
      final history = _messages
          .skip(1)
          .map((m) => {'role': m.role, 'content': m.text})
          .toList();
      final reply = await ref.read(aiRepositoryProvider).chat(
            message: text,
            courseId: widget.courseId,
            history: history.length > 10
                ? history.sublist(history.length - 10)
                : history,
          );
      if (!mounted) return;
      setState(() {
        _messages.add(AiChatMessage(role: 'assistant', text: reply.reply));
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _messages.add(
          const AiChatMessage(
            role: 'assistant',
            text: 'Lỗi kết nối. Trợ lý AI tạm không khả dụng, thử lại sau.',
          ),
        );
      });
    } finally {
      if (mounted) {
        setState(() => _loading = false);
        _scrollToEnd();
        await _persist();
      }
    }
  }

  Future<void> _clear() async {
    await ref.read(aiChatHistoryStoreProvider).clear(
          userId: _userId,
          courseId: widget.courseId,
        );
    if (!mounted) return;
    setState(() {
      _messages
        ..clear()
        ..add(
          const AiChatMessage(
            role: 'assistant',
            text: 'Đã xoá hội thoại. Bạn cần hỗ trợ gì?',
          ),
        );
    });
    await _persist();
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) return;
      _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Trợ lý AI'),
            Text(
              widget.courseId != null
                  ? 'Ngữ cảnh khóa học'
                  : 'Hỗ trợ học tập',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Xóa hội thoại',
            onPressed: _clear,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              controller: _scroll,
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
              itemCount: _messages.length + (_loading ? 1 : 0),
              itemBuilder: (context, index) {
                if (_loading && index == _messages.length) {
                  return const Align(
                    alignment: Alignment.centerLeft,
                    child: Padding(
                      padding: EdgeInsets.symmetric(vertical: 8),
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  );
                }
                final msg = _messages[index];
                final isUser = msg.role == 'user';
                return Align(
                  alignment:
                      isUser ? Alignment.centerRight : Alignment.centerLeft,
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 10,
                    ),
                    constraints: BoxConstraints(
                      maxWidth: MediaQuery.of(context).size.width * 0.82,
                    ),
                    decoration: BoxDecoration(
                      color: isUser
                          ? AppColors.primary600
                          : theme.colorScheme.surfaceContainerHighest,
                      borderRadius: BorderRadius.only(
                        topLeft: const Radius.circular(16),
                        topRight: const Radius.circular(16),
                        bottomLeft: Radius.circular(isUser ? 16 : 4),
                        bottomRight: Radius.circular(isUser ? 4 : 16),
                      ),
                    ),
                    child: Text(
                      msg.text,
                      style: TextStyle(
                        color: isUser
                            ? Colors.white
                            : theme.colorScheme.onSurface,
                        height: 1.4,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          if (_messages.length <= 1 && !_loading)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _quick
                    .map(
                      (q) => ActionChip(
                        label: Text(q),
                        onPressed: () => _send(q),
                      ),
                    )
                    .toList(),
              ),
            ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _input,
                      minLines: 1,
                      maxLines: 4,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: InputDecoration(
                        hintText: 'Nhập câu hỏi…',
                        filled: true,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(24),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 12,
                        ),
                      ),
                    ),
                  ),
                  AppSpacing.w8,
                  FilledButton(
                    onPressed: _loading ? null : () => _send(),
                    style: FilledButton.styleFrom(
                      shape: const CircleBorder(),
                      padding: const EdgeInsets.all(14),
                    ),
                    child: const Icon(Icons.arrow_upward_rounded),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
