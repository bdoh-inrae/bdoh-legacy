<?php

namespace Irstea\BdohAdminBundle\Filter;

use Doctrine\DBAL\Types\Types;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\Type\Operator\DateOperatorType;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\DoctrineORMAdminBundle\Filter\Filter;
use Symfony\Component\Form\Extension\Core\Type\DateType;

/**
 * Le filtre d'une date, avec les choix de Sonata 3 : « est égal à », les comparaisons, et « est vide »,
 * « n'est pas vide », que Sonata 4 a retirés. Comme dans Sonata 3, ces deux derniers ne s'appliquent
 * que si une date est saisie, dont ils ne tiennent pas compte.
 */
final class DateFilter extends Filter
{
    public const TYPE_NULL = 6;
    public const TYPE_NOT_NULL = 7;

    private const OPERATORS = [
        DateOperatorType::TYPE_GREATER_EQUAL => '>=',
        DateOperatorType::TYPE_GREATER_THAN  => '>',
        DateOperatorType::TYPE_EQUAL         => '=',
        DateOperatorType::TYPE_LESS_EQUAL    => '<=',
        DateOperatorType::TYPE_LESS_THAN     => '<',
    ];

    public function filter(ProxyQueryInterface $query, string $alias, string $field, FilterData $data): void
    {
        $value = $data->getValue();
        if (!$value instanceof \DateTime) {
            return;
        }
        $type = $data->getType() ?? DateOperatorType::TYPE_EQUAL;

        if (self::TYPE_NULL === $type || self::TYPE_NOT_NULL === $type) {
            $this->applyWhere($query, \sprintf('%s.%s IS %s', $alias, $field, self::TYPE_NULL === $type ? 'NULL' : 'NOT NULL'));

            return;
        }
        if (!isset(self::OPERATORS[$type])) {
            return;
        }

        $parameterName = $this->getNewParameterName($query);
        // « est égal à » : toute la journée
        if (DateOperatorType::TYPE_EQUAL === $type) {
            $endParameterName = $this->getNewParameterName($query);
            $this->applyWhere($query, \sprintf('%s.%s >= :%s', $alias, $field, $parameterName));
            $this->applyWhere($query, \sprintf('%s.%s < :%s', $alias, $field, $endParameterName));
            $query->getQueryBuilder()->setParameter($parameterName, $value, Types::DATETIME_MUTABLE);
            $query->getQueryBuilder()->setParameter($endParameterName, (clone $value)->add(new \DateInterval('P1D')), Types::DATETIME_MUTABLE);

            return;
        }

        $this->applyWhere($query, \sprintf('%s.%s %s :%s', $alias, $field, self::OPERATORS[$type], $parameterName));
        $query->getQueryBuilder()->setParameter($parameterName, $value, Types::DATETIME_MUTABLE);
    }

    public function getDefaultOptions(): array
    {
        return ['field_type' => DateType::class];
    }

    public function getFormOptions(): array
    {
        return [
            'field_type'       => $this->getFieldType(),
            'field_options'    => $this->getFieldOptions(),
            'label'            => $this->getLabel(),
            'operator_type'    => DateOperatorType::class,
            'operator_options' => [
                'choices' => [
                    'label_date_type_equal'         => DateOperatorType::TYPE_EQUAL,
                    'label_date_type_greater_equal' => DateOperatorType::TYPE_GREATER_EQUAL,
                    'label_date_type_greater_than'  => DateOperatorType::TYPE_GREATER_THAN,
                    'label_date_type_less_equal'    => DateOperatorType::TYPE_LESS_EQUAL,
                    'label_date_type_less_than'     => DateOperatorType::TYPE_LESS_THAN,
                    'label_date_type_null'          => self::TYPE_NULL,
                    'label_date_type_not_null'      => self::TYPE_NOT_NULL,
                ],
            ],
        ];
    }
}
