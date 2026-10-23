<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\TopicCategory;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationTopicCategory extends HistoriqueAdministration
{
    /**
     * @var TopicCategory
     */
    protected $topicCategory;

    /**
     * @return TopicCategory
     */
    public function getTopicCategory()
    {
        return $this->topicCategory;
    }

    /**
     * @param TopicCategory $topicCategory
     *
     * @return HistoriqueAdministrationTopicCategory
     */
    public function setTopicCategory(TopicCategory $topicCategory)
    {
        $this->topicCategory = $topicCategory;

        return $this;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.topicCategory') . $translator->trans('deux_points') . ' ' .
            $this->getTopicCategory()
        );
    }
}
