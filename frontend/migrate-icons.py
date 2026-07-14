#!/usr/bin/env python3
import os
import re

# Lucide → PrimeIcons mapping
ICON_MAP = {
    'Check': 'check', 'X': 'times', 'ChevronDown': 'chevron-down', 'ChevronUp': 'chevron-up',
    'ChevronLeft': 'chevron-left', 'ChevronRight': 'chevron-right', 'ArrowLeft': 'arrow-left',
    'ArrowRight': 'arrow-right', 'ArrowUp': 'arrow-up', 'Plus': 'plus', 'Minus': 'minus',
    'Search': 'search', 'Calendar': 'calendar', 'Clock': 'clock', 'Eye': 'eye', 'EyeOff': 'eye-slash',
    'Loader': 'spinner', 'RefreshCw': 'refresh', 'RotateCcw': 'replay', 'Sun': 'sun', 'Moon': 'moon',
    'Bell': 'bell', 'BellOff': 'bell-slash', 'Menu': 'bars', 'Settings': 'cog', 'User': 'user',
    'Users': 'users', 'Mail': 'envelope', 'Lock': 'lock', 'Trash2': 'trash', 'Trash': 'trash',
    'Edit': 'pencil', 'Edit2': 'pencil', 'Pencil': 'pencil', 'Save': 'save', 'Download': 'download',
    'Upload': 'upload', 'CloudUpload': 'cloud-upload', 'Star': 'star', 'Info': 'info-circle',
    'HelpCircle': 'question-circle', 'CheckCircle': 'check-circle', 'CheckCircle2': 'check-circle',
    'CircleCheckBig': 'check-circle', 'XCircle': 'times-circle', 'AlertCircle': 'exclamation-circle',
    'CircleAlert': 'exclamation-circle', 'Home': 'home', 'LayoutDashboard': 'th-large',
    'LogOut': 'sign-out', 'ExternalLink': 'external-link', 'Link2': 'link', 'BookOpen': 'book',
    'GraduationCap': 'graduation-cap', 'FileText': 'file', 'File': 'file', 'Folder': 'folder',
    'FolderOpen': 'folder-open', 'FolderPlus': 'folder-plus', 'FolderX': 'folder', 'Image': 'image',
    'PlayCircle': 'play-circle', 'Play': 'play', 'PauseCircle': 'pause-circle', 'MonitorPlay': 'video',
    'ShoppingBag': 'shopping-bag', 'ShoppingCart': 'shopping-cart', 'CreditCard': 'credit-card',
    'Banknote': 'money-bill', 'ReceiptText': 'receipt', 'Award': 'verified', 'Trophy': 'trophy',
    'Medal': 'star', 'Target': 'circle', 'Zap': 'bolt', 'Sparkles': 'sparkles', 'Flame': 'bolt',
    'Coins': 'money-bill', 'Building': 'building', 'Building2': 'building', 'MapPin': 'map-marker',
    'CalendarCheck': 'calendar', 'ClipboardList': 'list', 'Layers': 'clone', 'BarChart2': 'chart-bar',
    'TrendingUp': 'arrow-up', 'ThumbsUp': 'thumbs-up', 'ThumbsDown': 'thumbs-down', 'Hash': 'hashtag',
    'MoreVertical': 'ellipsis-v', 'Inbox': 'inbox', 'FileDown': 'download', 'FileSpreadsheet': 'file-excel',
    'UserPlus': 'user-plus', 'UserCheck': 'user', 'Frown': 'face-frown', 'Bot': 'comment',
    'Route': 'directions', 'ScanSearch': 'search', 'ShieldCheck': 'shield', 'ShieldAlert': 'shield',
    'GripVertical': 'bars', 'ZoomIn': 'search-plus', 'Settings2': 'cog',
}

def migrate_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    original = content
    
    # Remove lucide imports
    content = re.sub(r"import\s+\{[^}]*\}\s+from\s+['\"]lucide-vue-next['\"]", 
                     "// Icons removed - using PrimeIcons", content)
    content = re.sub(r"import\s+\*\s+as\s+\w+\s+from\s+['\"]lucide-vue-next['\"]",
                     "// Icons removed - using PrimeIcons", content)
    
    # Replace icon components (simple pattern - may need manual fix for complex cases)
    for lucide, prime in ICON_MAP.items():
        # <Icon :size="X" /> → <i class="pi pi-xxx" style="font-size:...px" />
        content = re.sub(
            rf'<{lucide}\s+:size="(\d+)"[^/>]*/>', 
            lambda m: f'<i class="pi pi-{prime}" style="font-size:{int(m.group(1))/16}rem" />',
            content
        )
        # Simple <Icon /> without props
        content = re.sub(rf'<{lucide}\s*/>', f'<i class="pi pi-{prime}" />', content)
    
    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'✅ {filepath}')
        return True
    return False

def walk_dir(root):
    count = 0
    for dirpath, _, filenames in os.walk(root):
        for filename in filenames:
            if filename.endswith(('.vue', '.ts')):
                filepath = os.path.join(dirpath, filename)
                if migrate_file(filepath):
                    count += 1
    return count

if __name__ == '__main__':
    app_dir = os.path.join(os.path.dirname(__file__), 'app')
    count = walk_dir(app_dir)
    print(f'\n🎉 Migrated {count} files')

