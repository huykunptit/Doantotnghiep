<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'       => 'Công nghệ thông tin',
                'slug'       => 'cong-nghe-thong-tin',
                'icon'       => 'code',
                'sort_order' => 1,
                'legacy'     => ['lap-trinh-cntt'],
                'children'   => [
                    ['name' => 'Lập trình & phần mềm', 'slug' => 'lap-trinh-phan-mem', 'sort_order' => 1, 'legacy' => ['web-dev', 'mobile-dev', 'lap-trinh-cntt']],
                    ['name' => 'Cơ sở dữ liệu & hệ thống', 'slug' => 'csdl-he-thong', 'sort_order' => 2, 'legacy' => ['database', 'devops']],
                    ['name' => 'Trí tuệ nhân tạo', 'slug' => 'tri-tue-nhan-tao', 'sort_order' => 3, 'legacy' => []],
                ],
            ],
            [
                'name'       => 'Quản trị kinh doanh',
                'slug'       => 'quan-tri-kinh-doanh',
                'icon'       => 'briefcase',
                'sort_order' => 2,
                'legacy'     => ['kinh-doanh'],
                'children'   => [
                    ['name' => 'Marketing & thương mại', 'slug' => 'marketing-thuong-mai', 'sort_order' => 1, 'legacy' => ['marketing', 'kinh-doanh']],
                    ['name' => 'Quản lý dự án', 'slug' => 'quan-ly-du-an', 'sort_order' => 2, 'legacy' => ['quan-ly-du-an']],
                ],
            ],
            [
                'name'       => 'Điện tử viễn thông',
                'slug'       => 'dien-tu-vien-thong',
                'icon'       => 'cpu',
                'sort_order' => 3,
                'legacy'     => ['thiet-ke', 'ngoai-ngu'],
                'children'   => [
                    ['name' => 'Mạng & viễn thông', 'slug' => 'mang-vien-thong', 'sort_order' => 1, 'legacy' => ['thiet-ke', 'ui-ux']],
                    ['name' => 'Điện tử & IoT', 'slug' => 'dien-tu-iot', 'sort_order' => 2, 'legacy' => ['do-hoa', 'ngoai-ngu', 'tieng-anh', 'tieng-nhat']],
                ],
            ],
        ];

        $keepIds = [];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            $legacy = $categoryData['legacy'] ?? [];
            unset($categoryData['children'], $categoryData['legacy']);

            $parent = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
            $keepIds[] = $parent->id;

            // Gộp khóa học từ danh mục cũ vào ngành mới
            $this->reassignBySlugs($legacy, $parent->id);

            foreach ($children as $childData) {
                $childLegacy = $childData['legacy'] ?? [];
                unset($childData['legacy']);

                $child = Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    array_merge($childData, ['parent_id' => $parent->id])
                );
                $keepIds[] = $child->id;
                $this->reassignBySlugs($childLegacy, $child->id);
            }
        }

        // Xóa danh mục cũ không còn dùng (sau khi đã chuyển khóa học)
        Category::query()
            ->whereNotIn('id', $keepIds)
            ->orderByDesc('parent_id')
            ->get()
            ->each(function (Category $cat) use ($keepIds) {
                $fallback = $keepIds[0] ?? null;
                if ($fallback) {
                    Course::query()->where('category_id', $cat->id)->update(['category_id' => $fallback]);
                }
                $cat->delete();
            });

        $this->command?->info('CategorySeeder: 3 ngành CNTT / QTKD / ĐTVT đã cập nhật.');
    }

    private function reassignBySlugs(array $slugs, int $targetId): void
    {
        if ($slugs === []) {
            return;
        }

        $ids = Category::query()->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        Course::query()->whereIn('category_id', $ids)->update(['category_id' => $targetId]);
    }
}
