<?php

namespace Shopware\Components\SwagImportExport\DbAdapters;

class ArticlesTranslationsDbAdapter implements DataDbAdapter
{

    /**
     * Shopware\Components\Model\ModelManager
     */
    protected $manager;

    /**
     * Shopware\Models\Article\Article
     */
    protected $repository;
    
    /**
     * @return type
     */
    protected $db;

    public function getDefaultColumns()
    {
        return array(
            'd.ordernumber as articleNumber',
            't.languageID as languageId',
            't.name as name',
            't.keywords as keywords',
            't.description as description',
            't.description_long as descriptionLong',
            't.metaTitle',
        );
    }

    public function readRecordIds($start, $limit, $filter)
    {
        $manager = $this->getManager();

        $builder = $manager->createQueryBuilder();

        $builder->select('t.id');
        $builder->from('Shopware\Models\Translation\Translation', 't')
                ->leftJoin('Shopware\Models\Article\Article', 'article', \Doctrine\ORM\Query\Expr\Join::WITH, 'article.id=t.key')
                ->join('article.details', 'detail')
                ->where("t.type = 'article'")
                ->andWhere('detail.kind = 1');

        $builder->setFirstResult($start)
                ->setMaxResults($limit);

        $records = $builder->getQuery()->getResult();

        $result = array_map(
                function($item){
                    return $item['id'];
                }, $records
        );

        return $result;
    }

    public function read($ids, $columns)
    {
        if (!$ids && empty($ids)) {
            $message = SnippetsHelper::getNamespace()
                    ->get('adapters/translations/no_ids', 'Can not read translations without ids.');
            throw new \Exception($message);
        }

        if (!$columns && empty($columns)) {
            $message = SnippetsHelper::getNamespace()
                    ->get('adapters/translations/no_column_names', 'Can not read translations without column names.');
            throw new \Exception($message);
        }

        $manager = $this->getManager();

        $builder = $manager->createQueryBuilder();
        $builder->select(array('detail.number as articleNumber', 't.data', 't.key as articleId ', 't.localeId as languageId'))
                ->from('Shopware\Models\Translation\Translation', 't')
                ->leftJoin('Shopware\Models\Article\Article', 'article', \Doctrine\ORM\Query\Expr\Join::WITH, 'article.id=t.key')
                ->join('article.details', 'detail')
                ->where('t.id IN (:ids)')
                ->andWhere('detail.kind = 1')
                ->setParameter('ids', $ids);

        $translations = $builder->getQuery()->getResult();

        $result['default'] = $this->prepareTranslations($translations);

        return $result;
    }

    /**
     * Processing serialized object data 
     *
     * @param array $translations
     * @return array
     */
    protected function prepareTranslations($translations)
    {
        $translationFields = array(
            "txtArtikel" => "name",
            "txtzusatztxt" => "additionaltext",
            "txtshortdescription" => "description",
            "txtlangbeschreibung" => "descriptionLong",
            "txtkeywords" => "keywords",
            "metaTitle" => "metaTitle"
        );

        if (!empty($translations)) {
            foreach ($translations as $index => $translation) {
                $objectdata = unserialize($translation['data']);

                if (!empty($objectdata)) {
                    foreach ($objectdata as $key => $value) {
                        if (isset($translationFields[$key])) {
                            $translations[$index][$translationFields[$key]] = $value;
                        }
                    }
                    unset($translations[$index]['data']);
                }
            }
        }

        return $translations;
    }

    public function write($records)
    {
        $queryValues = array();
        
        foreach ($records['default'] as $index => $record) {

            if (!isset($record['articleNumber'])) {
                $message = SnippetsHelper::getNamespace()
                        ->get('adapters/ordernumber_required', 'Order number is required.');
                throw new \Exception($message);
            }
            
            if (isset($record['languageId'])) {
                $shop = $this->getManager()->find('Shopware\Models\Shop\Shop', $record['languageId']);
            }
            
            if (!$shop) {
                $message = SnippetsHelper::getNamespace()
                        ->get('adapters/articlesTranslations/lang_id_not_found', 'Language with id %s does not exists');
                throw new \Exception($message);
            }

            $articleDetail = $this->getRepository()->findOneBy(array('number' => $record['articleNumber']));

            if (!$articleDetail) {
                $message = SnippetsHelper::getNamespace()
                    ->get('adapters/article_number_not_found', 'Article with order number %s doen not exists');
                throw new \Exception(sprintf($message, $record['articleNumber']));
            }

            $articleId = (int) $articleDetail->getArticle()->getId();
            $languageID = (int) $record['languageId'];
            $name = $this->prepareValue($record['title']);
            $description = $this->prepareValue($record['description']);
            $descriptionLong = $this->prepareValue($record['descriptionLong']);
            $keywords = $this->prepareValue($record['keywords']);
            $descriptionClear = $this->prepareValue($record['descriptionClear']);
            $attr1 = $this->prepareValue($record['attr1']);
            $attr2 = $this->prepareValue($record['attr2']);
            $attr3 = $this->prepareValue($record['attr3']);
            $attr4 = $this->prepareValue($record['attr4']);
            $attr5 = $this->prepareValue($record['attr5']);
            
            $value = "($articleId, $languageID, '$name', '$description', '$descriptionLong', '$keywords',
                       '$descriptionClear', '$attr1', '$attr2', '$attr3', '$attr4', '$attr5' )";
            $queryValues[] = $value;

            unset($articleDetail);
        }
        
        $queryValues = implode(',', $queryValues);
        
        $query = "REPLACE INTO s_articles_translations (articleID, languageID, name, description, description_long,
                                                       keywords, description_clear, attr1, attr2, attr3, attr4, attr5) 
                 VALUES $queryValues";
        
        $this->getDb()->query($query);
    }

    protected function prepareValue($value)
    {
        $value = $value !== null ? ($value) : '';
        
        $value = mysql_escape_string($value);
        
        return $value;
    }

    /**
     * @return array
     */
    public function getSections()
    {
        return array(
            array('id' => 'default', 'name' => 'default ')
        );
    }

    /**
     * @param string $section
     * @return mix
     */
    public function getColumns($section)
    {
        $method = 'get' . ucfirst($section) . 'Columns';

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        return false;
    }

    /**
     * Returns article detail repository
     * 
     * @return Shopware\Models\Article\Detail
     */
    public function getRepository()
    {
        if ($this->repository === null) {
            $this->repository = $this->getManager()->getRepository('Shopware\Models\Article\Detail');
        }

        return $this->repository;
    }

    /**
     * Returns entity manager
     * 
     * @return Shopware\Components\Model\ModelManager
     */
    public function getManager()
    {
        if ($this->manager === null) {
            $this->manager = Shopware()->Models();
        }

        return $this->manager;
    }

    public function getDb()
    {
        if ($this->db === null) {
            $this->db = Shopware()->Db();
        }
        
        return $this->db;
    }

}
