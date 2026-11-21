<?php

namespace App\Enums;

/**
 * Kelompok kategori pengeluaran operasional (OPEX).
 */
enum ExpenseType: string
{
    case Bandwidth = 'bandwidth';
    case Equipment = 'equipment';
    case Field = 'field';
    case Salary = 'salary';
    case Other = 'other';
}
