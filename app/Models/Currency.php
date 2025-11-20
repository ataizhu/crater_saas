<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Currency extends Model
{
    use HasFactory;

    protected $guarded = [
        'id'
    ];

    /**
     * Переопределяем метод newBaseQueryBuilder для предотвращения запросов на центральном домене.
     * Currency - это таблица тенанта, она не должна запрашиваться на центральном домене (Filament админка).
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        // Если это центральный домен (не тенант), возвращаем пустой query builder
        // который не будет выполнять реальные запросы к несуществующей таблице
        if (!tenancy()->initialized) {
            // Создаем "пустой" query builder, который возвращает пустые результаты
            return new class($connection) extends \Illuminate\Database\Query\Builder {
                public function get($columns = ['*'])
                {
                    return collect([]);
                }

                public function first($columns = ['*'])
                {
                    return null;
                }

                public function find($id, $columns = ['*'])
                {
                    return null;
                }

                public function value($column)
                {
                    return null;
                }

                public function pluck($column, $key = null)
                {
                    return collect([]);
                }

                public function count($columns = '*')
                {
                    return 0;
                }

                public function exists()
                {
                    return false;
                }

                public function doesntExist()
                {
                    return true;
                }
            };
        }

        return parent::newBaseQueryBuilder();
    }
}
