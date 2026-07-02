import { createTable } from '@tanstack/table-core';

const columns = [
  { id: 'code', accessorKey: 'code', header: 'Mã lớp' },
  { id: 'name', accessorKey: 'name', header: 'Tên lớp' },
  { id: 'cohort', accessorKey: 'cohort', header: 'Khóa học' },
  { id: 'curriculum', accessorKey: 'curriculum', header: 'Lộ trình đào tạo (CTĐT)' },
  { id: 'advisor', accessorKey: 'advisor', header: 'Cố vấn học tập' },
  { id: 'students_count', accessorKey: 'students_count', header: 'Sĩ số' },
  { id: 'status', accessorKey: 'status', header: 'Trạng thái' },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
];

try {
  const table = createTable({
    data: [],
    columns,
    getCoreRowModel: () => ({ rows: [], flatRows: [], rowsById: {} })
  });
  console.log("Success! Columns processed correctly. Columns length:", table.options.columns.length);
} catch (err) {
  console.error("Error encountered:", err);
}
