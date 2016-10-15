<?php
// app/code/local/Envato/WidgetLinks/Block/Links.php
class Webmasterei_Landingpage_Block_TopProducts
    extends Mage_Catalog_Block_Product_List
    implements Mage_Widget_Block_Interface
{

    /**
     * Retrieve loaded category collection
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     **/
    protected function _getNewProductCollection($category,$maxItems)
    {
        $todayDate  = Mage::app()->getLocale()->date()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds());

        $collection = $this->_addProductAttributesAndPrices($collection)
            ->addStoreFilter()
            ->addCategoryFilter($category)
            ->addUrlRewrite()
            ->addAttributeToFilter('news_from_date', array('date' => true, 'to' => $todayDate))
            ->addAttributeToFilter('news_to_date', array('or'=> array(
                0 => array('date' => true, 'from' => $todayDate),
                1 => array('is' => new Zend_Db_Expr('null')))
            ), 'left')
            ->addAttributeToSort('news_from_date', 'desc')
            ->setPageSize($maxItems);

        $this->setProductCollection($collection);

        return $collection;
    }

    protected function _getSpecialProductsColletion($category,$maxItems)
    {
        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToSelect('*')
            ->addCategoryFilter($category)
            ->addUrlRewrite()
            ->addFieldToFilter('visibility', array(
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
            )) //showing just products visible in catalog or both search and catalog
            ->addFinalPrice()
            ->addAttributeToSort('special_price', 'asc') //in case we would like to sort products by price
            ->getSelect()
            ->where('price_index.final_price < price_index.price')
            ->limit($maxItems) //we can specify how many products we want to show on this page
//                        ->order(new Zend_Db_Expr('RAND()')) //in case we would like to sort products randomly

        ;

        return $collection;
    }
    public function getMostViewedProducts($category,$maxItems)
    {
        // store ID
        $storeId    = Mage::app()->getStore()->getId();

        // get most viewed products for current category
        $collection = Mage::getResourceModel('reports/product_collection')
            ->addAttributeToSelect('*')
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addViewsCount()
            ->addUrlRewrite()
            ->addCategoryFilter($category)
            ->setPageSize($maxItems);

        Mage::getSingleton('catalog/product_status')
            ->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')
            ->addVisibleInCatalogFilterToCollection($collection);

        return $collection;
    }

    public function getBestsellerProducts($category,$maxItems)
    {
        $storeId = (int) Mage::app()->getStore()->getId();

        // Date
        $date = new Zend_Date();
        $toDate = $date->setDay(1)->getDate()->get('Y-MM-dd');
        $fromDate = $date->subMonth(1)->getDate()->get('Y-MM-dd');

        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addStoreFilter()
            ->addPriceData()
            ->addTaxPercents()
            ->addUrlRewrite()
            ->addCategoryFilter($category)
            ->setPageSize($maxItems);

        $collection->getSelect()
            ->joinLeft(
                array('aggregation' => $collection->getResource()->getTable('sales/bestsellers_aggregated_monthly')),
                "e.entity_id = aggregation.product_id AND aggregation.store_id={$storeId} AND aggregation.period BETWEEN '{$fromDate}' AND '{$toDate}'",
                array('SUM(aggregation.qty_ordered) AS sold_quantity')
            )
            ->group('e.entity_id')
            ->order(array('sold_quantity DESC', 'e.created_at'));

        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($collection);

        return $collection;
    }
    protected function getWidgetCategory($categoryId)
    {
        $storeId = Mage::app()->getStore()->getStoreId();
        $currentCategory =  Mage::registry('current_category');
        if($categoryId) {
        $category = Mage::getModel('catalog/category')->load($categoryId);
        }
        elseif($currentCategory) {
            $category = $currentCategory;
        }  else {
            $categoryId = Mage::app()->getStore($storeId)->getRootCategoryId();
            $category = Mage::getModel('catalog/category')->load($categoryId);
        }
        return $category;
    }


}// Mage_Catalog_Block_Product_New
