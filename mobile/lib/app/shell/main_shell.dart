import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

class _Tab {
  const _Tab({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    required this.root,
    required this.prefixes,
  });

  final String label;
  final IconData icon;
  final IconData selectedIcon;

  /// Route opened when the tab is tapped.
  final String root;

  /// Locations that highlight this tab.
  final List<String> prefixes;
}

/// Single source of truth for the bottom navigation (max 5 tabs).
const _tabs = <_Tab>[
  _Tab(
    label: 'Trang chủ',
    icon: Icons.home_outlined,
    selectedIcon: Icons.home_rounded,
    root: '/home',
    prefixes: ['/home'],
  ),
  _Tab(
    label: 'Học tập',
    icon: Icons.school_outlined,
    selectedIcon: Icons.school_rounded,
    root: '/my-courses',
    prefixes: ['/my-courses', '/catalog'],
  ),
  _Tab(
    label: 'Lộ trình',
    icon: Icons.route_outlined,
    selectedIcon: Icons.route_rounded,
    root: '/paths',
    prefixes: ['/paths'],
  ),
  _Tab(
    label: 'Thi cử',
    icon: Icons.assignment_outlined,
    selectedIcon: Icons.assignment_rounded,
    root: '/exams',
    prefixes: ['/exams'],
  ),
  _Tab(
    label: 'Hồ sơ',
    icon: Icons.person_outline_rounded,
    selectedIcon: Icons.person_rounded,
    root: '/profile',
    prefixes: ['/profile'],
  ),
];

class MainShell extends StatelessWidget {
  const MainShell({super.key, required this.child});

  final Widget child;

  int _indexFor(String location) {
    final i = _tabs.indexWhere((t) => t.prefixes.any(location.startsWith));
    return i < 0 ? 0 : i;
  }

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final selectedIndex = _indexFor(location);

    // Back on a non-home tab returns to Home before leaving the app.
    return PopScope(
      canPop: selectedIndex == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go(_tabs.first.root);
      },
      child: Scaffold(
        body: child,
        bottomNavigationBar: NavigationBar(
          selectedIndex: selectedIndex,
          onDestinationSelected: (index) {
            if (index == selectedIndex) return;
            HapticFeedback.selectionClick();
            context.go(_tabs[index].root);
          },
          destinations: [
            for (final t in _tabs)
              NavigationDestination(
                icon: Icon(t.icon),
                selectedIcon: Icon(t.selectedIcon),
                label: t.label,
              ),
          ],
        ),
      ),
    );
  }
}
