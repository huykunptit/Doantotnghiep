#!/usr/bin/env python3
"""
migrate-icons.py  –  Lucide-vue-next  →  PrimeIcons
Handles:
  • <Icon /> and <Icon :size="N" />
  • attrs in any order: class, v-if/v-else/v-else-if, :stroke-width, :key, style, :color, title
  • multi-line tags (flags=re.DOTALL)
  • JS variable references:  icon: Foo  /  { 'key': Foo }  /  || Foo
  • icon: Type map in object literals
"""

import os
import re

# ── Lucide → PrimeIcons mapping ────────────────────────────────────────────────
ICON_MAP = {
    # Navigation / arrows
    'ArrowLeft': 'arrow-left', 'ArrowRight': 'arrow-right', 'ArrowUp': 'arrow-up',
    'ArrowDown': 'arrow-down', 'ArrowUpDown': 'arrows-v', 'ChevronDown': 'chevron-down',
    'ChevronUp': 'chevron-up', 'ChevronLeft': 'chevron-left', 'ChevronRight': 'chevron-right',
    'ChevronsUpDown': 'arrows-v', 'ExternalLink': 'external-link', 'Link2': 'link',
    'TrendingUp': 'arrow-up', 'TrendingDown': 'arrow-down', 'Route': 'directions',

    # Status / alerts
    'Check': 'check', 'CheckCheck': 'check-circle', 'CheckCircle': 'check-circle',
    'CheckCircle2': 'check-circle', 'CircleCheckBig': 'check-circle',
    'X': 'times', 'XCircle': 'times-circle', 'XOctagon': 'times-circle',
    'AlertCircle': 'exclamation-circle', 'AlertTriangle': 'exclamation-triangle',
    'CircleAlert': 'exclamation-circle', 'Info': 'info-circle',
    'ShieldCheck': 'shield', 'ShieldAlert': 'shield',
    'Activity': 'chart-line', 'Loader': 'spinner', 'Loader2': 'spinner',

    # Media / files
    'Play': 'play', 'PlayCircle': 'play-circle', 'PauseCircle': 'pause-circle',
    'MonitorPlay': 'video', 'Video': 'video', 'Image': 'image',
    'File': 'file', 'FileText': 'file', 'FileCheck': 'file-check',
    'FileCode': 'file-code', 'FileDown': 'download', 'FileSpreadsheet': 'file-excel',
    'FileQuestion': 'question-circle', 'Film': 'video',
    'Upload': 'upload', 'UploadCloud': 'cloud-upload', 'CloudUpload': 'cloud-upload',
    'Download': 'download', 'Save': 'save',
    'Folder': 'folder', 'FolderOpen': 'folder-open', 'FolderPlus': 'folder-plus', 'FolderX': 'folder',

    # UI actions
    'Plus': 'plus', 'PlusCircle': 'plus-circle', 'Minus': 'minus',
    'Edit': 'pencil', 'Edit2': 'pencil', 'Pencil': 'pencil',
    'Trash': 'trash', 'Trash2': 'trash', 'Copy': 'copy',
    'Eye': 'eye', 'EyeOff': 'eye-slash', 'ZoomIn': 'search-plus',
    'Search': 'search', 'ScanSearch': 'search', 'Filter': 'filter',
    'Settings': 'cog', 'Settings2': 'cog', 'Sliders': 'sliders-h',
    'RefreshCw': 'refresh', 'RefreshCcw': 'refresh', 'RotateCcw': 'replay',
    'GripVertical': 'bars', 'GripHorizontal': 'bars', 'MoreVertical': 'ellipsis-v',
    'MoreHorizontal': 'ellipsis-h', 'Menu': 'bars', 'Sidebar': 'bars',
    'Hammer': 'wrench', 'Rocket': 'send', 'Wand2': 'magic',
    'Square': 'stop-circle', 'Box': 'box',

    # People / auth
    'User': 'user', 'Users': 'users', 'UserPlus': 'user-plus',
    'UserCheck': 'user', 'UserX': 'user-minus',
    'LogOut': 'sign-out', 'LogIn': 'sign-in',
    'Lock': 'lock', 'Unlock': 'lock-open', 'Key': 'key',
    'Mail': 'envelope', 'Inbox': 'inbox',

    # Commerce / finance
    'ShoppingBag': 'shopping-bag', 'ShoppingCart': 'shopping-cart',
    'CreditCard': 'credit-card', 'Banknote': 'money-bill',
    'DollarSign': 'dollar', 'Coins': 'money-bill', 'ReceiptText': 'receipt',
    'Gift': 'gift', 'Ticket': 'ticket',

    # Education / achievement
    'GraduationCap': 'graduation-cap', 'BookOpen': 'book', 'BookMarked': 'bookmark',
    'Award': 'verified', 'Trophy': 'trophy', 'Medal': 'star',
    'ClipboardList': 'list', 'Layers': 'clone', 'Package': 'box',
    'Database': 'database', 'Cpu': 'desktop', 'Terminal': 'terminal',

    # Calendar / time
    'Calendar': 'calendar', 'CalendarCheck': 'calendar', 'CalendarDays': 'calendar',
    'Clock': 'clock', 'History': 'history', 'Timer': 'clock',

    # Charts / data
    'BarChart2': 'chart-bar', 'BarChart3': 'chart-bar',
    'TrendingUp': 'arrow-up', 'TrendingDown': 'arrow-down',
    'PieChart': 'chart-pie', 'LineChart': 'chart-line',

    # Nature / theme
    'Sun': 'sun', 'Moon': 'moon', 'Sparkles': 'sparkles', 'Zap': 'bolt',
    'Flame': 'bolt', 'Target': 'circle', 'Compass': 'compass',
    'Globe': 'globe', 'Map': 'map', 'MapPin': 'map-marker',
    'Lightbulb': 'lightbulb',

    # Misc
    'Bell': 'bell', 'BellOff': 'bell-slash', 'Home': 'home',
    'LayoutDashboard': 'th-large', 'Building': 'building', 'Building2': 'building',
    'HelpCircle': 'question-circle', 'HardDrive': 'hdd',
    'Star': 'star', 'Frown': 'face-frown', 'Smile': 'face-smile',
    'ThumbsUp': 'thumbs-up', 'ThumbsDown': 'thumbs-down',
    'Hash': 'hashtag', 'Bot': 'comment', 'Briefcase': 'briefcase',
    'CheckSquare': 'check-square', 'List': 'list',
}


def extract_attr(attrs_str: str, attr: str):
    """Extract value of a specific attribute from an attrs string."""
    m = re.search(rf'\b{attr}=["\']([^"\']*)["\']', attrs_str)
    return m.group(1) if m else None


def replace_icon_tag(lucide: str, prime: str, content: str) -> str:
    """
    Replace <LucideName ...attrs... /> with <i class="pi pi-xxx" style="..." .../>
    Preserves: v-if, v-else, v-else-if, :key, class (appended), style (merged).
    """
    # Match self-closing tags, allowing multi-line attributes
    pattern = rf'<{re.escape(lucide)}(\s[^>]*)?\s*/>'

    def sub(m: re.Match) -> str:
        attrs = m.group(1) or ''

        # Size → font-size
        size_m = re.search(r':size=["\'](\d+)["\']', attrs)
        size = int(size_m.group(1)) if size_m else 16
        fs = f'{size / 16:.4g}rem'

        # Existing style (merge, avoid duplicating font-size)
        style_m = re.search(r'style=["\']([^"\']*)["\']', attrs)
        existing_style = style_m.group(1).strip().rstrip(';') if style_m else ''
        if 'font-size' in existing_style:
            style_val = existing_style
        elif existing_style:
            style_val = f'font-size:{fs};{existing_style}'
        else:
            style_val = f'font-size:{fs}'

        # Extra classes
        class_m = re.search(r'class=["\']([^"\']*)["\']', attrs)
        extra_class = (' ' + class_m.group(1)) if class_m else ''

        # Conditional directives (preserve as-is)
        directives = ''
        for d in ('v-if', 'v-else-if', 'v-else', ':key', 'v-for', 'aria-hidden', 'aria-label'):
            if d == 'v-else' and 'v-else' in attrs and 'v-else-if' not in attrs:
                directives += ' v-else'
            else:
                dm = re.search(rf'\b{re.escape(d)}=["\']([^"\']*)["\']', attrs)
                if dm:
                    directives += f' {d}="{dm.group(1)}"'

        return f'<i{directives} class="pi pi-{prime}{extra_class}" style="{style_val}" />'

    return re.sub(pattern, sub, content, flags=re.DOTALL)


def replace_js_references(lucide: str, prime: str, content: str) -> str:
    """
    Replace JS/TS usages of icon components as values:
      icon: Foo,          →  icon: 'prime-name',
      || Foo              →  || 'prime-name'
      { 'key': Foo }      →  { 'key': 'prime-name' }
      iconMap[key] || Foo →  iconMap[key] || 'prime-name'
    Only replaces when the name appears as a standalone identifier value (not in JSX/template tags).
    """
    # Replace in object values:  : ComponentName  (after colon, not inside a tag)
    content = re.sub(
        rf'(:\s*){re.escape(lucide)}\b',
        lambda m: f"{m.group(1)}'{prime}'",
        content
    )
    # Replace as map value:  'key': ComponentName
    content = re.sub(
        rf"('[^']+'\s*:\s*){re.escape(lucide)}\b",
        lambda m: f"{m.group(1)}'{prime}'",
        content
    )
    # Replace as fallback:  || ComponentName
    content = re.sub(
        rf'\|\|\s*{re.escape(lucide)}\b',
        f"|| '{prime}'",
        content
    )
    return content


def migrate_file(filepath: str) -> bool:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content

    # ── 1. Remove lucide imports ──────────────────────────────────────────────
    content = re.sub(
        r"import\s+\{[^}]*\}\s+from\s+['\"]lucide-vue-next['\"][^\n]*\n?",
        '',
        content,
    )
    content = re.sub(
        r"import\s+\*\s+as\s+\w+\s+from\s+['\"]lucide-vue-next['\"][^\n]*\n?",
        '',
        content,
    )
    # Clean up leftover "// Icons removed" comment lines from previous runs
    content = re.sub(r'^// Icons removed[^\n]*\n', '', content, flags=re.MULTILINE)

    # ── 2. Replace template tags + JS references ──────────────────────────────
    for lucide, prime in ICON_MAP.items():
        content = replace_icon_tag(lucide, prime, content)
        content = replace_js_references(lucide, prime, content)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'✅  {filepath}')
        return True
    return False


def walk_dir(root: str) -> int:
    count = 0
    for dirpath, _, filenames in os.walk(root):
        if 'node_modules' in dirpath:
            continue
        for filename in filenames:
            if filename.endswith(('.vue', '.ts')):
                filepath = os.path.join(dirpath, filename)
                if migrate_file(filepath):
                    count += 1
    return count


if __name__ == '__main__':
    app_dir = os.path.join(os.path.dirname(__file__), 'app')
    print(f'🔍  Scanning {app_dir} …')
    count = walk_dir(app_dir)
    print(f'\n🎉  Migrated {count} files')

