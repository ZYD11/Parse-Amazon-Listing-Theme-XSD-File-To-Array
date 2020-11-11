<?php
class parseXsd {
    public $base_elem_tag_elem = array(
    "simpleType",
    "complexType",
    //"unique"
    );

    public $tag_attr_arr = array(
    "name",
//    "type",
    "minOccurs",
    "maxOccurs",
    "base",
    "use",
    "ref",
    "default",
    "fixed"
    );

    public $restrictions_tag_elem = array(
    "enumeration",
    "fractionDigits",
    "length",
    "maxExclusive",
    "maxInclusive",
    "maxLength",
    "minExclusive",
    "minInclusive",
    "minLength",
    "pattern",
    "totalDigits",
    "whiteSpace"
    );

    public $simpleType_tag_elem = array(
    "union",
    "restriction"=>array("enumeration"),
    );

    public $complexType_tag_elem = array(
    //"annotation",
    "simpleContent"=>array(
        "extension"=>array(
            "attribute"
        ),
    ),
    //"complexContent",
    //"group",
    //"all",
    "choice"=>array("element"),
    "sequence"=>array("element"),
    );

    public $data_type = array(
    "boolean",
    "double",
    "float",
    "byte",
    "decimal",
    "int",
    "integer",
    "long",
    "negativeInteger",
    "nonNegativeInteger",
    "nonPositiveInteger",
    "positiveInteger",
    "short",
    "unsignedLong",
    "unsignedInt",
    "unsignedShort",
    "unsignedByte",
    "date",
    "dateTime",
    "duration",
    "gDay",
    "gMonth",
    "gMonthDay",
    "gYear",
    "gYearMonth",
    "time",
    "normalizedString",
    "string"
    );

    public $parseXsdFile, $amazonBaseXsd;

    public $parseXsdDoc, $BaseXsdDoc;

    public $parseXsdXpath, $BaseXsdXpath;

    public $productType, $ProductTypeSpecifics = array();

    public $fileExt = ".xsd";

    public $prefix = "xsd:";

    public function __construct($parseXsdFile, $amazonBaseXsd="amzn-base")
    {
        $this->parseXsdFile = $parseXsdFile.$this->fileExt;
        $this->amazonBaseXsd = $amazonBaseXsd.$this->fileExt;

        $this->parseXsdDoc = new DOMDocument();
        $this->BaseXsdDoc = new DOMDocument();

        $this->parseXsdDoc->load($this->parseXsdFile);
        $this->parseXsdXpath = new DOMXPath($this->parseXsdDoc);

        $this->BaseXsdDoc->load($this->amazonBaseXsd);
        $this->BaseXsdXpath = new DOMXPath($this->BaseXsdDoc);
    }

    public function parseXsdParseData($AttrNodeName, $AttrNode){
        $attr_data = array();

        $attr_data = array_merge($this->parseNodeAttr($AttrNode), $attr_data);

        foreach ($this->base_elem_tag_elem as $tag_elem){
            if($AttrNode->getElementsByTagName($tag_elem)->length>0){
                if($tag_elem=="simpleType"){
                    $productTypeAttr = $AttrNode->getElementsByTagName("restriction");
                    foreach ($productTypeAttr as $productTypeAttrNode){
                        $RestrictionData = $this->getRestriction($productTypeAttrNode);
                        $attr_data       = array_merge($RestrictionData, $attr_data);

                        $baseType = $this->getBaseType($productTypeAttrNode);

                        if(in_array($baseType, $this->data_type)){
                            $attr_data['type'] = $baseType;
                            foreach ($productTypeAttrNode->getElementsByTagName("enumeration") as $enum_node){
                                $attr_data['enumeration'][] = $enum_node->getAttribute("value");
                            }
                        } else {

                            $baseNodeElem = $this->BaseXsdParse('//'.$this->prefix.'simpleType [@name="'. $baseType .'"]//'
                                                                .$this->prefix.'restriction');

                            if(empty($baseNodeElem)){
                                $baseNodeElem = $this->parseXsdParse('//'.$this->prefix.'simpleType [@name="'. $baseType .'"]//'
                                                                    .$this->prefix.'restriction');
                            }

                            if ($baseNodeElem) {
                                foreach ($baseNodeElem as $baseNode) {
                                    $baseNodeType = $this->getBaseType($baseNode);
                                    $RestrictionData = array();
                                    if (in_array($baseNodeType, $this->data_type)) {
                                        $attr_data['type'] = $baseNodeType;
                                        $RestrictionData = $this->getRestriction($baseNode);
                                    }

                                    $attr_data = array_merge($attr_data, array_unique($RestrictionData));
                                }
                            }
                        }
                    }
                }
            }
        }

        if(is_array($attr_data['enumeration'])){
            $attr_data['enumeration'] = array_unique($attr_data['enumeration']);
        }

        if(!empty($attr_data)) $attr_data['name'] = $AttrNodeName;

        return $attr_data;
    }

    public function parseXsdParse($matchRule){
        $matchResult = $this->parseXsdXpath->query($matchRule);
        if($matchResult->length>0) return $matchResult;

        return false;
    }

    public function LocalXsdParseData($AttrNodeType, $AttrNodeName){
        $attr_data = array();

        $baseNodeElem = $this->parseXsdParse('//'.$this->prefix.'complexType [@name="'.$AttrNodeType.'"]/*');

        if (!$baseNodeElem) {
            $baseNodeElem = $this->parseXsdParse('//'.$this->prefix.'simpleType [@name="'.$AttrNodeType.'"]//'.$this->prefix.'restriction');

            if (!$baseNodeElem) {

                $baseNodeElem = $this->parseXsdParse('//'.$this->prefix.'element [@name="'.$AttrNodeType.'"]');

                if ($baseNodeElem) {
                    foreach ($baseNodeElem as $baseNode){
                        $attr_data = array_merge($attr_data, $this->parseElementTag($baseNode));
                    }
                } else {
                    $attr_data =  $this->BaseXsdParseData($AttrNodeType, $AttrNodeName);
                }

            } else {

                foreach ($baseNodeElem as $baseNode) {
                    $attr_data = array_merge($attr_data, $this->parseSimpleTypeTag($baseNode));

                }
            }

        } else {

            foreach ($baseNodeElem as $baseNode) {
                $attr_data = array_merge($attr_data, $this->parseComplexTypeTag($baseNode));
            }
        }

        if(!empty($attr_data)) $attr_data['name'] = $AttrNodeName;

        return $attr_data;
    }

    public function BaseXsdParseData($AttrNodeType, $AttrNodeName){
        $attr_data = array();

        $baseNodeElem = $this->BaseXsdParse('//'.$this->prefix.'complexType [@name="'.$AttrNodeType.'"]//*');

        if (!$baseNodeElem) {
            $baseNodeElem = $this->BaseXsdParse('//'.$this->prefix.'simpleType [@name="'.$AttrNodeType.'"]//'.$this->prefix.'restriction');

            if (!$baseNodeElem) {

                $baseNodeElem = $this->BaseXsdParse('//'.$this->prefix.'element [@name="'.$AttrNodeType.'"]');

                if ($baseNodeElem) {
                    foreach ($baseNodeElem as $baseNode){
                        $attr_data = array_merge($attr_data, $this->parseElementTag($baseNode));
                    }
                }

            } else {

                foreach ($baseNodeElem as $baseNode) {
                    $attr_data = array_merge($attr_data, $this->parseSimpleTypeTag($baseNode));
                }
            }

        } else {

            foreach ($baseNodeElem as $baseNode) {
                $attr_data = array_merge($attr_data, $this->parseComplexTypeTag($baseNode));
            }
        }

        if(!empty($attr_data)) $attr_data['name'] = $AttrNodeName;

        return $attr_data;
    }

    public function BaseXsdParse($matchRule){
        $matchResult = $this->BaseXsdXpath->query($matchRule);
        if($matchResult->length>0) return $matchResult;

        return false;
    }

    public function parseProductType(){
        $this->productType[] = str_replace(".xsd", "", $this->parseXsdFile);

        $productTypeNode = $this->parseXsdXpath->query('//'.$this->prefix.'element [@name="ProductType"]//'.$this->prefix
                                                    .'complexType//'.$this->prefix.'choice/'.$this->prefix.'element');

        foreach ($productTypeNode as $node){
            $this->productType[] = $node->getAttribute("ref");
        }

        return $this->productType;
    }

    public function parseProductTypeSpecifics(){
        foreach ($this->productType as $product_type){
            $productTypeAttrNode = $this->parseXsdXpath->query('//'.$this->prefix.'element [@name="'.$product_type .'"]//'.
                                                                $this->prefix.'complexType//'.$this->prefix.'sequence//'
                                                                .$this->prefix.'element');
            foreach ($productTypeAttrNode as $AttrNode){
                $AttrNodeName = $AttrNode->getAttribute("name");

                if(empty($AttrNodeName)){
                    if(!in_array($AttrNode->getAttribute("ref"), $this->productType)) {
                        $AttrNodeName = $AttrNode->getAttribute("ref");
                    }
                }

                if(!in_array($AttrNodeName, array("Parentage","VariationData","VariationTheme","ProductType")) && !empty($AttrNodeName)){

                    $AttrNodeType = $this->getAttrType($AttrNode);

                    if(empty($AttrNodeType)) $AttrNodeType = $AttrNode->getAttribute("ref");

                    if(!empty($AttrNodeType)){

                        if(!in_array($AttrNodeType, $this->data_type, true)) {
                            $nodeAttr = $this->parseNodeAttr($AttrNode);

                            $attr_data = $this->BaseXsdParseData($AttrNodeType, $AttrNodeName);

                            if(empty($attr_data)) {
                                $attr_data = $this->LocalXsdParseData($AttrNodeType, $AttrNodeName);
                            }

                            if($attr_data) {
                                $attr_data = array_merge($nodeAttr, $attr_data);
                                $this->ProductTypeSpecifics[$product_type]['template_specifics'][] = $attr_data;
                            }

                        } else {
                            $attr_data = $this->parseNodeAttr($AttrNode);

                            if($attr_data) $this->ProductTypeSpecifics[$product_type]['template_specifics'][] = $attr_data;
                        }
                    } else {
                            $attr_data = $this->parseXsdParseData($AttrNodeName, $AttrNode);
                            if($attr_data) $this->ProductTypeSpecifics[$product_type]['template_specifics'][] = $attr_data;
                    }
                } else {

                    if($AttrNodeName=="VariationData"){
                        $this->ProductTypeSpecifics[$product_type]["VariationData"] = $this->parseNodeAttr($AttrNode);
                        unset($this->ProductTypeSpecifics[$product_type]["VariationData"]["name"]);
                    }

                    if($this->ProductTypeSpecifics[$product_type]["VariationData"]) {
                        if ($AttrNodeName == "Parentage") {
                            $this->ProductTypeSpecifics[$product_type]["VariationData"]['tag_elem'][] = $this->parseParentage($AttrNode);
                        } else if ($AttrNodeName == "VariationTheme") {
                            $this->ProductTypeSpecifics[$product_type]["VariationData"]['tag_elem'][] = $this->parseVariationTheme($AttrNode);
                        }
                    }
                }
            }

            if($product_type != str_replace($this->fileExt,"", $this->parseXsdFile)){
                $this->ProductTypeSpecifics[$product_type]["product_type"] = "Child";
            } else {
                $this->ProductTypeSpecifics[$product_type]["product_type"] = "Parent";
            }
        }
    }

    public function parseParentage($AttrNode){
        $ParentageData = array();

        $restrictionNode = $AttrNode->getElementsByTagName("restriction");
        if($restrictionNode->length>0){
            foreach ($restrictionNode as $restrictionNodeVal){
                $ParentageData = $this->parseSimpleTypeTag($restrictionNodeVal);
            }
        }
        if(!empty($ParentageData)) {
            $ParentageData = array_merge($ParentageData, $this->parseNodeAttr($AttrNode));
            $ParentageData['name'] = $AttrNode->getAttribute("name");
        }

        return $ParentageData;
    }

    public function parseVariationTheme($AttrNode){
        $VariationTheme = array();
        $restrictionNode = $AttrNode->getElementsByTagName("restriction");


        if($restrictionNode->length>0){
            foreach ($restrictionNode as $restrictionNodeVal){
                $VariationTheme = $this->parseSimpleTypeTag($restrictionNodeVal);
            }
        }

        if(!empty($VariationTheme)) {
            $VariationTheme = array_merge($VariationTheme, $this->parseNodeAttr($AttrNode));
            $VariationTheme['name'] = $AttrNode->getAttribute("name");
        }

        return $VariationTheme;
    }

    public function parseNodeAttr($AttrNode){
        $attr_data = array();
        foreach ($this->tag_attr_arr as $tag_attr_val) {
            $attr_val = $AttrNode->getAttribute($tag_attr_val);
            if($attr_val != ''){
                $attr_data[$tag_attr_val] = $attr_val;
            }
        }

        return $attr_data;
    }

    public function parseComplexTypeTag($baseNode){

        $attr_data = array();
        $nodeName = $baseNode->localName;
        $nodeChild = $this->complexType_tag_elem[$nodeName];

        if(!empty($nodeChild)) {
            foreach ($nodeChild as $tag_key => $tag_elem) {
                if ($nodeName == "simpleContent") {
                    $nextNode = $baseNode->getElementsByTagName($tag_key);
                    foreach ($nextNode as $nextNodeVal) {
                        $contentType = $this->getBaseType($nextNodeVal);

                        if (!in_array($contentType, $this->data_type, true)) {
                            //标签文本内容类型处理
                            $parseContent = $this->BaseXsdParseData($contentType, $tag_key);
                            if (empty($parseContent)) {
                                $parseContent = $this->LocalXsdParseData($contentType, $tag_key);
                            }

                            unset($parseContent['name']);
                            $attr_data['content_type'] = $parseContent;
                        } else {
                            $attr_data['content_type']['type'] = $nextNodeVal->getAttribute("base");
                        }

                        //标签属性处理
                        $lastNode = $nextNodeVal->getElementsByTagName($tag_elem[0]);
                        foreach ($lastNode as $lastNodeVal) {
                            $lastNodeType = $lastNodeVal->getAttribute("type");
                            $lastNodeName = $lastNodeVal->getAttribute("name");
                            $lastNodeUse = $lastNodeVal->getAttribute("use");

                            if (!in_array($lastNodeType, $this->data_type, true)) {

                                $parseResult = $this->BaseXsdParseData($lastNodeType, $lastNodeName);
                                if (empty($parseResult)) {
                                    $parseResult = $this->LocalXsdParseData($lastNodeType, $lastNodeName);
                                }

                                $attr_data['tag_attr'] = $parseResult;
                            } else {
                                $attr_data['tag_attr']['name'] = $lastNodeName;
                                $attr_data['tag_attr']['type'] = $lastNodeType;
                            }

                            if ($lastNodeUse) $attr_data['tag_attr']['use'] = $lastNodeUse;
                        }
                    }
                } else {

                    $nextNode = $baseNode->getElementsByTagName($tag_elem);
                    $complexTypeParent = array();
                    foreach ($nextNode as $nextNodeVal) {

                        $nextNodeType = $this->getAttrType($nextNodeVal);
                        $nextNodeDetailType = $nextNodeVal->getAttribute("type");
                        $nextNodeName = $nextNodeVal->getAttribute("name");

                        if (!empty($nextNodeType) &&
                            $nextNodeVal->parentNode->parentNode->parentNode->parentNode->localName != "sequence") {

                            if (!in_array($nextNodeType, $this->data_type, true)) {

                                $parseResult = $this->BaseXsdParseData($nextNodeType, $nextNodeName);
                                if (empty($parseResult)) {
                                    $parseResult = $this->LocalXsdParseData($nextNodeType, $nextNodeName);
                                }
                                $attr_data["tag_elem"][] = $parseResult;
                            } else {

                                $normalTypeData = array(
                                    "name" => $nextNodeName,
                                    "type" => $nextNodeDetailType
                                );

                                $normalTypeData = array_merge($this->parseNodeAttr($nextNodeVal), $normalTypeData);

                                $attr_data["tag_elem"][] = $normalTypeData;
                            }
                        } else {

                            if ($nextNodeVal->getElementsByTagName("complexType")->length > 0 ) {

                                if($nextNodeVal->getElementsByTagName("element")->length > 0 ) {
                                    $complexTypeParent["tag_elem"][] = $this->parseElementTag($nextNodeVal);
                                } else {
                                    if($nextNodeVal->getElementsByTagName("simpleContent")->length > 0) {
                                        $simpleNode = $nextNodeVal->getElementsByTagName("simpleContent");
                                        foreach ($simpleNode as $nodeData) {
                                            $complexTypeParent = array_merge($this->parseComplexTypeTag($nodeData),$complexTypeParent);
                                        }
                                    }
                                }

                                $complexTypeParent = array_merge($this->parseNodeAttr($nextNodeVal), $complexTypeParent);

                            } else if (!empty($nextNodeType)) {
                                $complexTypeParent["tag_elem"][] = array(
                                    "name" => $nextNodeName,
                                    "type" => $nextNodeDetailType
                                );
                            }
                        }
                    }

                    $attr_data["tag_elem"][] = $complexTypeParent;
                }
            }
        }

        return $attr_data;
    }

    public function parseSimpleTypeTag($baseNode){

        $attr_data = array();
        $baseNodeDataType = $this->getBaseType($baseNode);
        $baseType = $baseNode->getAttribute("base");

        if (!in_array($baseNodeDataType, $this->data_type, true)) {
            $attr_data = $this->BaseXsdParseData($baseNodeDataType, $baseNode->parentNode->parentNode->getAttribute("name"));
            if(empty($attr_data)){
                $attr_data = $this->LocalXsdParseData($baseNodeDataType, $baseNode->parentNode->parentNode->getAttribute("name"));
            }

            $attr_data = array_merge($attr_data, $this->getRestriction($baseNode));
        } else {
            $attr_data = $this->getRestriction($baseNode);
        }

        if(!empty($attr_data)) $attr_data['type'] = $baseType;

        return $attr_data;
    }

    public function parseElementTag($baseNode){
        $attr_data = array();

        foreach ($this->base_elem_tag_elem as $baseElemNode) {
            $baseElemNodeData = $baseNode->getElementsByTagName($baseElemNode);

            if($baseElemNodeData->length>0) {
                foreach ($baseElemNodeData as $baseElemNodeVal){
                    $localName = $baseElemNodeVal->localName;

                    if ($localName == "complexType") {

                        foreach ($this->complexType_tag_elem as $complexTypeKey => $nexNode) {
                            $complexTypeNode = $baseElemNodeVal->getElementsByTagName($complexTypeKey);
                            if ($complexTypeNode->length > 0) {
                                foreach ($complexTypeNode as $nodeVal) {
                                    if($complexTypeKey=="sequence" &&
                                        $nodeVal->parentNode->parentNode->parentNode->localName != "sequence") {
                                        $attr_data = $this->parseComplexTypeTag($nodeVal);
                                    }
                                }
                            }
                        }
                    } else if ($localName == "simpleType") {
                        $restrictionNode = $baseElemNodeVal->getElementsByTagName("restriction");
                        if($restrictionNode->length>0) {
                            foreach ($restrictionNode as $restrictionNodeVal) {
                                $attr_data = $this->parseSimpleTypeTag($restrictionNodeVal);
                            }
                        }
                    }
                }
            }
        }

        return $attr_data;
    }

    public function getRestriction($baseNode){
        $attr_data = array();
        foreach ($this->restrictions_tag_elem as $tag_elem) {
            if($baseNode->getElementsByTagName($tag_elem)->length>0){
                foreach ($baseNode->getElementsByTagName($tag_elem) as $tag_elem_data) {
                    if($tag_elem=='enumeration'){
                        $attr_data['enumeration'][] = $tag_elem_data->getAttribute("value");
                    } else {
                        $attr_data['restriction'][$tag_elem] = $tag_elem_data->getAttribute("value");
                    }
                }
            }
        }

        return $attr_data;
    }

    public function getBaseType($baseNode){
        return str_replace($this->prefix,"",$baseNode->getAttribute("base"));
    }

    public function getAttrType($AttrNode){
        return str_replace($this->prefix,"", $AttrNode->getAttribute("type"));
    }

    public function getResult(){
        $this->parseProductType();
        $this->parseProductTypeSpecifics();

        return $this->ProductTypeSpecifics;
    }
}

//$parseXsd = new parseXsd("FoodAndBeverages");
//
//$result = $parseXsd->getResult();

//echo "<pre>";
//print_r($result);
?>