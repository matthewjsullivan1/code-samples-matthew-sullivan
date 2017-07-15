<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
}
class EO_Create_Item_Tags {

	private $menuItems;
	private $orderProcessed;
	private $addSizeToTag;
	private $modifyTags;
	private $menuItemTags;


	public function __construct($menuItems, $orderProcessed, $modifyTags, $addSizeToTag) {
		$this->menuItems = $menuItems;
		$this->orderProcessed = $orderProcessed;
		$this->addSizeToTag = $addSizeToTag;
		$this->modifyTags = $modifyTags;
	}
	
	public function create_tags() {
		//Create tags for each ordered item 
		//Format array(['123.0.1'] => 'Coco', ...)
		$this->create_sorted_item_tags();
		return $this->menuItemTags;
	}

	private function get_first_key($key) {
		//get_first_key('123.0.1') = '123'
		//First integer is unique identifier for menu item
		return explode('.',$key)[0];
	}

	private function tag_already_taken($itemKey,$newTag,$menuItemTags) {
		//Checks to see if another item already uses $newTag
		$alreadyTaken = false;	
		foreach($menuItemTags as $key=>$tag) {
			if($newTag === $tag['tag'] && $this->get_first_key($itemKey) !== $this->get_first_key($key)) {	
				return true;
			}
		}
		return $alreadyTaken;
	}

	private function custom_tag($name) {
		//Returns a custom tag inputted by user
		$modifyTags = $this->modifyTags;
		if(!empty($modifyTags)) {
			foreach($modifyTags as $itemName => $customTag) {
				if(strpos($name, (string) $itemName) !== False) {
					return $customTag;
				}
			}
		}
		return 'no custom tag';
	}
	
	private function possible_tags($name) {
		//Return two possible tag options 
		//possible_tags('Chicken Tikka Masala') = array(['Chicke'],['CTM'])
		$tagOptions = array();
		$firstWord = explode(' ',trim($name))[0];
		$tagOptions[] = substr($firstWord, 0, 6);

		$firstChar = array();
		foreach(explode(' ',trim($name)) as $word){
			$firstChar[] = substr($word,0,1);
		}
		$tagOptions[] = substr(implode($firstChar), 0, 6);
		return $tagOptions;
	}

	private function tag_already_created_for_other_size($key, $menuItemTags) {
		//Returns tag for menu item if another size from same menu item already has tag
		//If menu item has multiple sizes, each tag should be the same
		foreach($menuItemTags as $keyTag => $tag) {
			if($this->get_first_key($key) === $this->get_first_key($keyTag)) {
				return $tag['tag'];
			}
		}
		return false;
	}
	
	private function create_tag($key, $item, &$menuItemTags) {
		//Modifies a references of menuItemTags and adds tag if necessary
		if(!array_key_exists($key, $menuItemTags)) {
			$otherSizeTag = $this->tag_already_created_for_other_size($key, $menuItemTags);
			if($otherSizeTag !== false) {
				$menuItemTags[$key]['tag'] = $otherSizeTag;
			} else {
				$customTag = $this->custom_tag($item['name']);
				if($customTag !== 'no custom tag' ) {
					$tagTaken = $this->tag_already_taken($key, $customTag, $menuItemTags);
					if(!$tagTaken) {
						$menuItemTags[$key]['tag'] = $customTag;
					} else {
						$menuItemTags[$key]['tag'] = $customTag . $this->get_first_key($key);
					}
				} else {
					$possibleTags = $this->possible_tags($item['name']);
					foreach($possibleTags as $tagOption) {
						$tagTaken = $this->tag_already_taken($key, $tagOption, $menuItemTags);
						if(!$tagTaken) {
							$menuItemTags[$key]['tag'] = $tagOption;
							return;
						}
					}	
					$menuItemTags[$key]['tag'] = $possibleTags[0] . $this->get_first_key($key);
				}
			}
		}
	}

	private function add_size_to_tag($key, $size, &$menuItemTags) {
		//Checks size of menu item to see if size identifier needs to be added to tag
		$addSizeToTag = $this->addSizeToTag;
		foreach($addSizeToTag as $sizeName => $sizeTag) {
			if(strpos($size, (string) $sizeName) !== false){
				$menuItemTags[$key]['tag'] .= $sizeTag;
				return;
			}		

		}
	}

	private function array_orderby() {
		//Sorts multidimensional arrays
		//Written by jimpoz on php.net
		$args = func_get_args();
		$data = array_shift($args);
		foreach ($args as $n => $field) {
		    if (is_string($field)) {
		        $tmp = array();
		        foreach ($data as $key => $row)
		            $tmp[$key] = $row[$field];
		        $args[$n] = $tmp;
		        }
		}
		$args[] = &$data;
		call_user_func_array('array_multisort', $args);
		return array_pop($args);
	}
	
	private function orderKey_to_menuItemKey($orderKey) {
		//orderKey_to_menuItemKey('123.0.1') = '123.0'
		return explode('.',$orderKey)[0] . '.' . explode('.', $orderKey)[1];
	}

	private function create_sorted_item_tags() {
		//Processes all ordered items and generates array of sorted tags

		$menuItems = $this->menuItems;
		$orderArray = $this->orderProcessed;
		$menuItemTags = array();

		//Creates tag for each ordered item
		foreach($orderArray as $order) {
			foreach($order['item'] as $key=>$orderedItem) {
				$this->create_tag($key, $orderedItem, $menuItemTags);
			}
		}
		
		//Checks to see if any sizes needed to be added to tag
		if(!empty($this->addSizeToTag)) {
			//Checks the size of each tag of menuItemTags and appends identifier if needed
			foreach($menuItemTags as $key=>$tag) {
				$size = $menuItems[$this->orderKey_to_menuItemKey($key)]['size'];
				$this->add_size_to_tag($key, $size, $menuItemTags);
			}
		}

		//Makes array with extra information needed to sort tags
		$menuItemTagsWithExtra = array();
		foreach($menuItemTags as $key => $tag) {
			$menuItemKey = $this->orderKey_to_menuItemKey($key);
			$menuItemTagswithExtra[] = array('ID' => $key,'tag' => $tag['tag'], 'lowercaseTag' => strtolower($tag['tag']), 'minprice' => $menuItems[$menuItemKey]['minprice']);
		}
		//Sorts tags by the minimum price of any size and then alphabetically to break ties
		$sorted = $this->array_orderby($menuItemTagswithExtra, 'minprice', SORT_DESC, 'lowercaseTag', SORT_ASC);
		$sortedMenuItemTags = array();
		foreach($sorted as $tagInfo) {
			$sortedMenuItemTags[$tagInfo['ID']] = $tagInfo['tag'];
		}
		$this->menuItemTags = $sortedMenuItemTags;
	}

}
