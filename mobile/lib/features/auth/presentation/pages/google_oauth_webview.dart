import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../providers/auth_provider.dart';
import '../../../../core/error/friendly_error.dart';

class GoogleOauthWebviewPage extends ConsumerStatefulWidget {
  const GoogleOauthWebviewPage({super.key});

  static const routeName = '/google-login-webview';

  @override
  ConsumerState<GoogleOauthWebviewPage> createState() => _GoogleOauthWebviewPageState();
}

class _GoogleOauthWebviewPageState extends ConsumerState<GoogleOauthWebviewPage> {
  WebViewController? _controller;
  bool _fetchingUrl = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchUrl();
  }

  Future<void> _fetchUrl() async {
    try {
      final url = await ref.read(authNotifierProvider.notifier).getGoogleLoginUrl();
      if (!mounted) return;
      if (url.isEmpty) {
        setState(() {
          _error = 'Không lấy được đường dẫn đăng nhập Google.';
          _fetchingUrl = false;
        });
        return;
      }
      
      final controller = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setNavigationDelegate(
          NavigationDelegate(
            onNavigationRequest: (NavigationRequest request) {
              final uri = Uri.parse(request.url);
              // Intercept redirect back to the web frontend redirect URI
              if (uri.path.contains('/auth/google')) {
                _handleCallback(uri.query);
                return NavigationDecision.prevent;
              }
              return NavigationDecision.navigate;
            },
          ),
        )
        ..loadRequest(Uri.parse(url));

      setState(() {
        _controller = controller;
        _fetchingUrl = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = friendlyErrorMessage(e);
        _fetchingUrl = false;
      });
    }
  }

  Future<void> _handleCallback(String query) async {
    setState(() => _fetchingUrl = true);
    try {
      await ref.read(authNotifierProvider.notifier).loginWithGoogle(query);
      if (!mounted) return;
      final authState = ref.read(authNotifierProvider);
      authState.whenOrNull(
        data: (user) {
          if (user != null) {
            context.go('/my-courses');
          } else {
            context.go('/login');
          }
        },
        error: (e, _) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(friendlyErrorMessage(e)), backgroundColor: Colors.red),
          );
          context.go('/login');
        },
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(friendlyErrorMessage(e)), backgroundColor: Colors.red),
      );
      context.go('/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Đăng nhập với Google'),
      ),
      body: _fetchingUrl
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: Colors.red),
                        const SizedBox(height: 12),
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: () {
                            setState(() {
                              _fetchingUrl = true;
                              _error = null;
                            });
                            _fetchUrl();
                          },
                          child: const Text('Thử lại'),
                        ),
                      ],
                    ),
                  ),
                )
              : WebViewWidget(controller: _controller!),
    );
  }
}
