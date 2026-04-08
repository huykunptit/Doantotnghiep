<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'       => 'Lập trình & CNTT',
                'slug'       => 'lap-trinh-cntt',
                'icon'       => 'code',
                'sort_order' => 1,
                'children'   => [
                    ['name' => 'Lập trình Web', 'slug' => 'web-dev', 'sort_order' => 1],
                    ['name' => 'Lập trình Mobile', 'slug' => 'mobile-dev', 'sort_order' => 2],
                    ['name' => 'Cơ sở dữ liệu', 'slug' => 'database', 'sort_order' => 3],
                    ['name' => 'DevOps & Cloud', 'slug' => 'devops', 'sort_order' => 4],
                ],
            ],
            [
                'name'       => 'Thiết kế',
                'slug'       => 'thiet-ke',
                'icon'       => 'paintbrush',
                'sort_order' => 2,
                'children'   => [
                    ['name' => 'UI/UX Design', 'slug' => 'ui-ux', 'sort_order' => 1],
                    ['name' => 'Đồ hoạ', 'slug' => 'do-hoa', 'sort_order' => 2],
                ],
            ],
            [
                'name'       => 'Kinh doanh',
                'slug'       => 'kinh-doanh',
                'icon'       => 'briefcase',
                'sort_order' => 3,
                'children'   => [
                    ['name' => 'Marketing', 'slug' => 'marketing', 'sort_order' => 1],
                    ['name' => 'Quản lý dự án', 'slug' => 'quan-ly-du-an', 'sort_order' => 2],
                ],
            ],
            [
                'name'       => 'Ngoại ngữ',
                'slug'       => 'ngoai-ngu',
                'icon'       => 'globe',
                'sort_order' => 4,
                'children'   => [
                    ['name' => 'Tiếng Anh', 'slug' => 'tieng-anh', 'sort_order' => 1],
                    ['name' => 'Tiếng Nhật', 'slug' => 'tieng-nhat', 'sort_order' => 2],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $parent = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );

            foreach ($children as $childData) {
                Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    array_merge($childData, ['parent_id' => $parent->id])
                );
            }
        }
    }
}
