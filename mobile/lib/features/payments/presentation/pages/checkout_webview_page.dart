import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

class CheckoutWebviewPage extends StatefulWidget {
  const CheckoutWebviewPage({super.key, required this.checkoutUrl});

  final String checkoutUrl;

  static const routeName = '/checkout-webview';

  @override
  State<CheckoutWebviewPage> createState() => _CheckoutWebviewPageState();
}

class _CheckoutWebviewPageState extends State<CheckoutWebviewPage> {
  WebViewController? _controller;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (NavigationRequest request) {
            final uri = Uri.parse(request.url);
            // Intercept checkout result urls
            if (uri.path.contains('/payment/result') || uri.path.contains('/payos/return')) {
              Navigator.pop(context, true);
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.checkoutUrl));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Thanh toán khóa học'),
      ),
      body: WebViewWidget(controller: _controller!),
    );
  }
}
