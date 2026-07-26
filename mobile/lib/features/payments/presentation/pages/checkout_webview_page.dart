import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/loading_overlay.dart';

class CheckoutWebviewPage extends StatefulWidget {
  const CheckoutWebviewPage({super.key, required this.checkoutUrl});

  final String checkoutUrl;

  static const routeName = '/checkout-webview';

  @override
  State<CheckoutWebviewPage> createState() => _CheckoutWebviewPageState();
}

class _CheckoutWebviewPageState extends State<CheckoutWebviewPage> {
  WebViewController? _controller;
  bool _isLoading = true;
  bool _hasError = false;
  bool _resultHandled = false;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) setState(() => _isLoading = true);
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _isLoading = false);
          },
          onWebResourceError: (error) {
            if (!mounted) return;
            // Sub-resource errors (ads, analytics, etc.) shouldn't block checkout;
            // only surface the retry screen for main-frame failures.
            if (error.isForMainFrame == false) return;
            setState(() {
              _isLoading = false;
              _hasError = true;
            });
          },
          onNavigationRequest: (NavigationRequest request) {
            final uri = Uri.tryParse(request.url);
            if (uri != null && _isPaymentReturnUrl(uri)) {
              _handlePaymentReturn(uri);
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.checkoutUrl));
  }

  bool _isPaymentReturnUrl(Uri uri) {
    return uri.path.contains('/payment/payos/callback') ||
        uri.path.contains('/payment/result') ||
        uri.path.contains('/payos/return');
  }

  /// PayOS appends its own status params (`code`, `status`, `cancel`,
  /// `orderCode`, ...) to whichever return/cancel URL was configured, so the
  /// definitive result is already available on this first redirect — we
  /// don't need to wait for the frontend's client-side routed result page.
  void _handlePaymentReturn(Uri uri) {
    if (_resultHandled) return;
    _resultHandled = true;

    final params = uri.queryParameters;
    final status = (params['status'] ?? '').toUpperCase();
    final code = params['code'];
    final isCancelled = (params['cancel'] ?? '').toLowerCase() == 'true' ||
        params['cancelled'] == '1' ||
        status == 'CANCELLED';
    final isFailed = status == 'FAILED' || status == 'EXPIRED';
    final isSuccess = !isCancelled &&
        !isFailed &&
        (status == 'PAID' || status == 'SUCCESS' || code == '00');

    // Let the return page finish loading (and sync the order status with the
    // backend) before closing the webview, so the user briefly sees the
    // success/failure confirmation instead of an abrupt pop.
    Future.delayed(const Duration(milliseconds: 1500), () {
      if (!mounted) return;
      Navigator.pop(context, isSuccess);
    });
  }

  void _retryLoad() {
    setState(() {
      _hasError = false;
      _isLoading = true;
    });
    _controller?.loadRequest(Uri.parse(widget.checkoutUrl));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Thanh toán khóa học'),
      ),
      body: Stack(
        children: [
          if (_controller != null) WebViewWidget(controller: _controller!),
          if (_hasError)
            Container(
              color: Theme.of(context).colorScheme.surface,
              alignment: Alignment.center,
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.wifi_off_rounded, size: 48, color: AppColors.error),
                    const SizedBox(height: 12),
                    const Text(
                      'Không thể tải trang thanh toán. Vui lòng kiểm tra kết nối mạng và thử lại.',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: _retryLoad,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Thử lại'),
                    ),
                  ],
                ),
              ),
            )
          else if (_isLoading)
            const LoadingOverlay(message: 'Đang tải trang thanh toán...'),
        ],
      ),
    );
  }
}
