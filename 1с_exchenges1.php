<?php
// ����� ��� �������� ��������� ������ �������������
$dir = 'simpla/cml/temp/';
// ��������� ��� ������ ��� ������ �������������
$full_update = true;
// �������� ��������� ������, ������������� ��� �����
$brand_option_name = '�������������';
$start_time = microtime(true);
$max_exec_time = min(30, @ini_get("max_execution_time"));
if(empty($max_exec_time))
	$max_exec_time = 30;
session_start();
chdir('../..');
include('api/Simpla.php');
$simpla = new Simpla();
if($simpla->request->get('type') == 'sale' && $simpla->request->get('mode') == 'checkauth')
{
	print "success\n";
	print session_name()."\n";
	print session_id();
}
if($simpla->request->get('type') == 'sale' && $simpla->request->get('mode') == 'init')
{
	$tmp_files = glob($dir.'*.*');
	if(is_array($tmp_files))
	foreach($tmp_files as $v)
	{
    	//unlink($v);
    }
	print "zip=no\n";
	print "file_limit=1000000\n";
}
if($simpla->request->get('type') == 'sale' && $simpla->request->get('mode') == 'file')
{
	$filename = $simpla->request->get('filename');
	
	
	$f = fopen($dir.$filename, 'ab');
	fwrite($f, file_get_contents('php://input'));
	fclose($f);
	$xml = simplexml_load_file($dir.$filename);	
	foreach($xml->�������� as $xml_order)
	{
		$order = new stdClass;
		$order->id = $xml_order->�����;
		$existed_order = $simpla->orders->get_order(intval($order->id));
		
		$order->date = $xml_order->����.' '.$xml_order->�����;
		$order->name = $xml_order->�����������->����������->������������;
		if(isset($xml_order->������������������->�����������������))
		foreach($xml_order->������������������->����������������� as $r)
		{
			switch ($r->������������) {
		    case '��������':
		    	$proveden = ($r->�������� == 'true');
		        break;
		    case '���������������':
		    	$udalen = ($r->�������� == 'true');
		        break;
			}
		}
		
		if($udalen)
			$order->status = 3;
		elseif($proveden)
			$order->status = 1;
		elseif(!$proveden)
			$order->status = 0;
		
		if($existed_order)
		{
			$simpla->orders->update_order($order->id, $order);
		}
		else
		{
			$order->id = $simpla->orders->add_order($order);
		}
		
		$purchases_ids = array();
		// ������
		foreach($xml_order->������->����� as $xml_product)
		{
			$purchase = null;
			//  Id ������ � �������� (���� ����) �� 1�
			$product_1c_id = $variant_1c_id = '';
			@list($product_1c_id, $variant_1c_id) = explode('#', $xml_product->��);
			if(empty($product_1c_id))
				$product_1c_id = '';
			if(empty($variant_1c_id))
				$variant_1c_id = '';
				
			// ���� �����
			$simpla->db->query('SELECT id FROM __products WHERE external_id=?', $product_1c_id);
			$product_id = $simpla->db->result('id');
			$simpla->db->query('SELECT id FROM __variants WHERE external_id=? AND product_id=?', $variant_1c_id, $product_id);
			$variant_id = $simpla->db->result('id');
				
			$purchase = new stdClass;		
			$purchase->order_id = $order->id;
			$purchase->product_id = $product_id;
			$purchase->variant_id = $variant_id;
			
			$purchase->sku = $xml_product->�������;			
			$purchase->product_name = $xml_product->������������;
			$purchase->amount = $xml_product->����������;
			$purchase->price = floatval($xml_product->�������������);
			
			if(isset($xml_product->������->������))
			{
				$discount = $xml_product->������->������->�������;
				$purchase->price = $purchase->price*(100-$discount)/100;
			}
			
			$simpla->db->query('SELECT id FROM __purchases WHERE order_id=? AND product_id=? AND variant_id=?', $order->id, $product_id, $variant_id);
			$purchase_id = $simpla->db->result('id');
			if(!empty($purchase_id))
				$purchase_id = $simpla->orders->update_purchase($purchase_id, $purchase);
			else
				$purchase_id = $simpla->orders->add_purchase($purchase);
			$purchases_ids[] = $purchase_id;
		}
		// ������ �������, ������� ��� � �����
		foreach($simpla->orders->get_purchases(array('order_id'=>intval($order->id))) as $purchase)
		{
			if(!in_array($purchase->id, $purchases_ids))
				$simpla->orders->delete_purchase($purchase->id);
		}
		
		$simpla->db->query('UPDATE __orders SET discount=0, total_price=? WHERE id=? LIMIT 1', $xml_order->�����, $order->id);
		
	}
	
	print "success";
	$simpla->settings->last_1c_orders_export_date = date("Y-m-d H:i:s");
}
if($simpla->request->get('type') == 'sale' && $simpla->request->get('mode') == 'query')
{
		$no_spaces = '<?xml version="1.0" encoding="utf-8"?>
							<���������������������� �����������="2.04" ����������������="' . date ( 'Y-m-d' )  . '"></����������������������>';
		$xml = new SimpleXMLElement ( $no_spaces );
		$orders = $simpla->orders->get_orders(array('modified_since'=>$simpla->settings->last_1c_orders_export_date));
		foreach($orders as $order)
		{
			$date = new DateTime($order->date);
			
			$doc = $xml->addChild ("��������");
			$doc->addChild ( "��", $order->id);
			$doc->addChild ( "�����", $order->id);
			$doc->addChild ( "����", $date->format('Y-m-d'));
			$doc->addChild ( "�����������", "����� ������" );
			$doc->addChild ( "����", "��������" );
			$doc->addChild ( "����", "1" );
			$doc->addChild ( "�����", $order->total_price);
			$doc->addChild ( "�����",  $date->format('H:i:s'));
			$doc->addChild ( "�����������", $order->comment);
			
			// �����������
			$k1 = $doc->addChild ( '�����������' );
			$k1_1 = $k1->addChild ( '����������' );
			$k1_2 = $k1_1->addChild ( "��", $order->name);
			$k1_2 = $k1_1->addChild ( "������������", $order->name);
			$k1_2 = $k1_1->addChild ( "����", "����������" );
			$k1_2 = $k1_1->addChild ( "������������������", $order->name );
			
			// ��� ���������
			$addr = $k1_1->addChild ('����������������');
			$addr->addChild ( '�������������', $order->address );
			$addrField = $addr->addChild ( '������������' );
			$addrField->addChild ( '���', '������' );
			$addrField->addChild ( '��������', 'RU' );
			$addrField = $addr->addChild ( '������������' );
			$addrField->addChild ( '���', '������' );
			$addrField->addChild ( '��������', $order->address );
			$contacts = $k1_1->addChild ( '��������' );
			$cont = $contacts->addChild ( '�������' );
			$cont->addChild ( '���', '�������' );
			$cont->addChild ( '��������', $order->phone );
			$cont = $contacts->addChild ( '�������' );
			$cont->addChild ( '���', '�����' );
			$cont->addChild ( '��������', $order->email );
			$purchases = $simpla->orders->get_purchases(array('order_id'=>intval($order->id)));
			$t1 = $doc->addChild ( '������' );
			foreach($purchases as $purchase)
			{
				if(!empty($purchase->product_id) && !empty($purchase->variant_id))
				{
					$simpla->db->query('SELECT external_id FROM __products WHERE id=?', $purchase->product_id);
					$id_p = $simpla->db->result('external_id');
					$simpla->db->query('SELECT external_id FROM __variants WHERE id=?', $purchase->variant_id);
					$id_v = $simpla->db->result('external_id');
					
					// ���� ��� �������� ����� ������ - ��������� ��� id
					if(!empty($id_p))
					{
						$id = $id_p;
					}
					else
					{
						$simpla->db->query('UPDATE __products SET external_id=id WHERE id=?', $purchase->product_id);
						$id = $purchase->product_id;
					}
					
					// ���� ��� �������� ����� �������� - ��������� ��� id
					if(!empty($id_v))
					{
						$id = $id.'#'.$id_v;
					}
					else
					{
						$simpla->db->query('UPDATE __variants SET external_id=id WHERE id=?', $purchase->variant_id);
						$id = $id.'#'.$purchase->variant_id;
					}
						
					$t1_1 = $t1->addChild ( '�����' );
					
					if($id)
						$t1_2 = $t1_1->addChild ( "��", $id);
					
					$t1_2 = $t1_1->addChild ( "�������", $purchase->sku);
					
					$name = $purchase->product_name;
					if($purchase->variant_name)
						$name .= " $purchase->variant_name $id";
					$t1_2 = $t1_1->addChild ( "������������", $name);
					$t1_2 = $t1_1->addChild ( "�������������", $purchase->price*(100-$order->discount)/100);
					$t1_2 = $t1_1->addChild ( "����������", $purchase->amount );
					$t1_2 = $t1_1->addChild ( "�����", $purchase->amount*$purchase->price*(100-$order->discount)/100);
					
					/*
					$t1_2 = $t1_1->addChild ( "������" );
					$t1_3 = $t1_2->addChild ( "������" );
					$t1_4 = $t1_3->addChild ( "�����", $purchase->amount*$purchase->price*(100-$order->discount)/100);
					$t1_4 = $t1_3->addChild ( "������������", "true" );
					*/
					
					$t1_2 = $t1_1->addChild ( "������������������" );
					$t1_3 = $t1_2->addChild ( "�����������������" );
					$t1_4 = $t1_3->addChild ( "������������", "���������������" );
					$t1_4 = $t1_3->addChild ( "��������", "�����" );
	
					$t1_2 = $t1_1->addChild ( "������������������" );
					$t1_3 = $t1_2->addChild ( "�����������������" );
					$t1_4 = $t1_3->addChild ( "������������", "���������������" );
					$t1_4 = $t1_3->addChild ( "��������", "�����" );
				}
			}
			
			// ��������
			if($order->delivery_price>0 && !$order->separate_delivery)
			{
				$t1 = $t1->addChild ( '�����' );
				$t1->addChild ( "��", 'ORDER_DELIVERY');
				$t1->addChild ( "������������", '��������');
				$t1->addChild ( "�������������", $order->delivery_price);
				$t1->addChild ( "����������", 1 );
				$t1->addChild ( "�����", $order->delivery_price);
				$t1_2 = $t1->addChild ( "������������������" );
				$t1_3 = $t1_2->addChild ( "�����������������" );
				$t1_4 = $t1_3->addChild ( "������������", "���������������" );
				$t1_4 = $t1_3->addChild ( "��������", "������" );
				$t1_2 = $t1->addChild ( "������������������" );
				$t1_3 = $t1_2->addChild ( "�����������������" );
				$t1_4 = $t1_3->addChild ( "������������", "���������������" );
				$t1_4 = $t1_3->addChild ( "��������", "������" );
				
			}
			
			// ������			
			if($order->status == 1)
			{
				$s1_2 = $doc->addChild ( "������������������" );
				$s1_3 = $s1_2->addChild ( "�����������������" );
				$s1_3->addChild ( "������������", "������ ������" );
				$s1_3->addChild ( "��������", "[N] ������" );
			}
			if($order->status == 2)
			{
				$s1_2 = $doc->addChild ( "������������������" );
				$s1_3 = $s1_2->addChild ( "�����������������" );
				$s1_3->addChild ( "������������", "������ ������" );
				$s1_3->addChild ( "��������", "[F] ���������" );
			}
			if($order->status == 3)
			{
				$s1_2 = $doc->addChild ( "������������������" );
				$s1_3 = $s1_2->addChild ( "�����������������" );
				$s1_3->addChild ( "������������", "�������" );
				$s1_3->addChild ( "��������", "true" );
			}			
		}
		header ( "Content-type: text/xml; charset=utf-8" );
		print "\xEF\xBB\xBF";
		print $xml->asXML ();
		$simpla->settings->last_1c_orders_export_date = date("Y-m-d H:i:s");
}
if($simpla->request->get('type') == 'sale' && $simpla->request->get('mode') == 'success')
{
		$simpla->settings->last_1c_orders_export_date = date("Y-m-d H:i:s");
}
if($simpla->request->get('type') == 'catalog' && $simpla->request->get('mode') == 'checkauth')
{
	print "success\n";
	print session_name()."\n";
	print session_id();
}
if($simpla->request->get('type') == 'catalog' && $simpla->request->get('mode') == 'init')
{	
	$tmp_files = glob($dir.'*.*');
	if(is_array($tmp_files))
	foreach($tmp_files as $v)
	{
    	unlink($v);
    }
    unset($_SESSION['last_1c_imported_variant_num']);
    unset($_SESSION['last_1c_imported_product_num']);
    unset($_SESSION['features_mapping']);
    unset($_SESSION['categories_mapping']);
    unset($_SESSION['brand_id_option']);    
   	print "zip=no\n";
	print "file_limit=1000000\n";
}
if($simpla->request->get('type') == 'catalog' && $simpla->request->get('mode') == 'file')
{
	$filename = basename($simpla->request->get('filename'));
	$f = fopen($dir.$filename, 'ab');
	fwrite($f, file_get_contents('php://input'));
	fclose($f);
	print "success\n";
} 
 
if($simpla->request->get('type') == 'catalog' && $simpla->request->get('mode') == 'import')
{
	$filename = basename($simpla->request->get('filename'));
	
	if($filename === 'import.xml')
	{
		// ��������� � �������� (������ � ������ ������� �������� ��������)
		if(!isset($_SESSION['last_1c_imported_product_num']))
		{
			$z = new XMLReader;
			$z->open($dir.$filename);		
			while ($z->read() && $z->name !== '�������������');
			$xml = new SimpleXMLElement($z->readOuterXML());
			$z->close();
			import_categories($xml);
			import_features($xml);
		}
		
		// ������ 			
		$z = new XMLReader;
		$z->open($dir.$filename);
		
		while ($z->read() && $z->name !== '�����');
		
		// ��������� �����, �� ������� ������������
		$last_product_num = 0;
		if(isset($_SESSION['last_1c_imported_product_num']))
			$last_product_num = $_SESSION['last_1c_imported_product_num'];
		
		// ����� �������� ������
		$current_product_num = 0;
		while($z->name === '�����')
		{
			if($current_product_num >= $last_product_num)
			{
				$xml = new SimpleXMLElement($z->readOuterXML());
				// ������
				import_product($xml);
				
				$exec_time = microtime(true) - $start_time;
				if($exec_time+1>=$max_exec_time)
				{
					header ( "Content-type: text/xml; charset=utf-8" );
					print "\xEF\xBB\xBF";
					print "progress\r\n";
					print "��������� �������: $current_product_num\r\n";
					$_SESSION['last_1c_imported_product_num'] = $current_product_num;
					exit();
				}
			}
			$z->next('�����');
			$current_product_num ++;
		}
		$z->close();
		print "success";
		//unlink($dir.$filename);
		unset($_SESSION['last_1c_imported_product_num']);				
	}
	elseif($filename === 'offers.xml')
	{
		// ��������			
		$z = new XMLReader;
		$z->open($dir.$filename);
		
		while ($z->read() && $z->name !== '�����������');
		
		// ��������� �������, �� ������� ������������
		$last_variant_num = 0;
		if(isset($_SESSION['last_1c_imported_variant_num']))
			$last_variant_num = $_SESSION['last_1c_imported_variant_num'];
		
		// ����� �������� ������
		$current_variant_num = 0;
		while($z->name === '�����������')
		{
			if($current_variant_num >= $last_variant_num)
			{
				$xml = new SimpleXMLElement($z->readOuterXML());
				// ��������
				import_variant($xml);
				
				$exec_time = microtime(true) - $start_time;
				if($exec_time+1>=$max_exec_time)
				{
					header ( "Content-type: text/xml; charset=utf-8" );
					print "\xEF\xBB\xBF";
					print "progress\r\n";
					print "��������� ������� �����������: $current_variant_num\r\n";
					$_SESSION['last_1c_imported_variant_num'] = $current_variant_num;
					exit();
				}
			}
			$z->next('�����������');
			$current_variant_num ++;
		}
		$z->close();
		print "success";
		//unlink($dir.$filename);
		unset($_SESSION['last_1c_imported_variant_num']);				
	}
}
function import_categories($xml, $parent_id = 0)
{
	global $simpla;
	global $dir;
	if(isset($xml->������->������))
	foreach ($xml->������->������ as $xml_group)
	{
		$simpla->db->query('SELECT id FROM __categories WHERE external_id=?', $xml_group->��);
		$category_id = $simpla->db->result('id');
		if(empty($category_id))
			$category_id = $simpla->categories->add_category(array('parent_id'=>$parent_id, 'external_id'=>$xml_group->��, 'url'=>translit($xml_group->������������), 'name'=>$xml_group->������������, 'meta_title'=>$xml_group->������������, 'meta_keywords'=>$xml_group->������������, 'meta_description'=>$xml_group->������������ ));
		$_SESSION['categories_mapping'][strval($xml_group->��)] = $category_id;
		import_categories($xml_group, $category_id);
	}
}
function import_features($xml)
{
	global $simpla;
	global $dir;
	global $brand_option_name;
	
	$property = array();
	if(isset($xml->��������->��������������������))
		$property = $xml->��������->��������������������;
		
	if(isset($xml->��������->��������))
		$property = $xml->��������->��������;
		
	foreach ($property as $xml_feature)
	{
		// ���� �������� �������� ������������� �������
		if($xml_feature->������������ == $brand_option_name)
		{
			// �������� � ������ �� �������� � ��������������
			$_SESSION['brand_option_id'] = strval($xml_feature->��);		
		}
		// ����� ������������ ��� ������� �������� ������
		else
		{
			$simpla->db->query('SELECT id FROM __features WHERE name=?', strval($xml_feature->������������));
			$feature_id = $simpla->db->result('id');
			if(empty($feature_id))
				$feature_id = $simpla->features->add_feature(array('name'=>strval($xml_feature->������������)));
			$_SESSION['features_mapping'][strval($xml_feature->��)] = $feature_id;
			if($xml_feature->����������� == '����������')
			{
				foreach($xml_feature->����������������->���������� as $val)
				{
					$_SESSION['features_values'][strval($val->����������)] = strval($val->��������);
				}
			}
		}
	}
}
function import_product($xml_product)
{
	global $simpla;
	global $dir;
	global $brand_option_name;
	global $full_update;
	// ������
	//  Id ������ � �������� (���� ����) �� 1�
	@list($product_1c_id, $variant_1c_id) = explode('#', $xml_product->��);
	if(empty($variant_1c_id))
		$variant_1c_id = '';
	
	// �� ���������
	if(isset($xml_product->������->��))
	$category_id = $_SESSION['categories_mapping'][strval($xml_product->������->��)];
	
	
	// �������������� �������
	$variant_id = null;
	$variant = new stdClass;
	$values = array();
	if(isset($xml_product->��������������������->��������������������))
	foreach($xml_product->��������������������->�������������������� as $xml_property)
		$values[] = $xml_property->��������;
	if(!empty($values))
		$variant->name = implode(', ', $values);
	$variant->sku = (string)$xml_product->�������;
	$variant->external_id = $variant_1c_id;
	
	// ���� �����
	$simpla->db->query('SELECT id FROM __products WHERE external_id=?', $product_1c_id);
	$product_id = $simpla->db->result('id');
	if(empty($product_id) && !empty($variant->sku))
	{
		$simpla->db->query('SELECT product_id, id FROM __variants WHERE sku=?', $variant->sku);
		$res = $simpla->db->result();
		if(!empty($res))
		{
			$product_id = $res->product_id;
			$variant_id = $res->id;
		}
	}
	
	// ���� ������ ������ �� �������		
	if(empty($product_id))
	{
		// ��������� �����
		$description = '';
		if(!empty($xml_product->��������))
			$description = $xml_product->��������;
		$product_id = $simpla->products->add_product(array('external_id'=>$product_1c_id, 'url'=>translit($xml_product->������������), 'name'=>$xml_product->������������, 'meta_title'=>$xml_product->������������, 'meta_keywords'=>$xml_product->������������, 'meta_description'=>$xml_product->$description,  'annotation'=>$description, 'body'=>$description));
		
		// ��������� ����� � ���������
		if(isset($category_id))
		$simpla->categories->add_product_category($product_id, $category_id);
	
		// ��������� ����������� ������
		if(isset($xml_product->��������))
		{
			foreach($xml_product->�������� as $img)
			{
				$image = basename($xml_product->��������);
				if(!empty($image) && is_file($dir.$image) && is_writable($simpla->config->original_images_dir))
				{
					rename($dir.$image, $simpla->config->original_images_dir.$image);
					$simpla->products->add_image($product_id, $image);
				}
			}
		}
	}
	//���� ������� �����
	else
	{
		if(empty($variant_id) && !empty($variant_1c_id))
		{
			$simpla->db->query('SELECT id FROM __variants WHERE external_id=? AND product_id=?', $variant_1c_id, $product_id);
			$variant_id = $simpla->db->result('id');
		}
		elseif(empty($variant_id) && empty($variant_1c_id))
		{
			$simpla->db->query('SELECT id FROM __variants WHERE product_id=?', $product_id);
			$variant_id = $simpla->db->result('id');		
		}
		
		// ��������� �����
		if($full_update)
		{
			$p = new stdClass();
			if(!empty($xml_product->��������))
			{
				$description = strval($xml_product->��������);
				$p->meta_description = $description;
				$p->meta_description = $description;
				$p->annotation = $description;
				$p->body = $description;
			}
			$p->external_id = $product_1c_id;
			$p->url = translit($xml_product->������������);
			$p->name = $xml_product->������������;
			$p->meta_title = $xml_product->������������;
			$p->meta_keywords = $xml_product->������������;
			$product_id = $simpla->products->update_product($product_id, $p);
			
			// ��������� ��������� ������
			if(isset($category_id) && !empty($product_id))
			{
   	    		$query = $simpla->db->placehold('DELETE FROM __products_categories WHERE product_id=?', $product_id);
   	    		$simpla->db->query($query);
				$simpla->categories->add_product_category($product_id, $category_id);
			}
			
		}
		
		// ��������� ����������� ������
		if(isset($xml_product->��������))
		{
			foreach($xml_product->�������� as $img)
			{
				$image = basename($img);
				if(!empty($image) && is_file($dir.$image) && is_writable($simpla->config->original_images_dir))
				{
					$simpla->db->query('SELECT id FROM __images WHERE product_id=? ORDER BY position LIMIT 1', $product_id);
					$img_id = $simpla->db->result('id');
					if(!empty($img_id))
						$simpla->products->delete_image($img_id);
					rename($dir.$image, $simpla->config->original_images_dir.$image);
					$simpla->products->add_image($product_id, $image);
				}
			}
		}
		
	}
	
	// ���� �� ������ �������, ��������� ������� ���� � ������
	if(empty($variant_id))
	{
		$variant->product_id = $product_id;
		$variant->stock = 0;
		$variant_id = $simpla->variants->add_variant($variant);
	}
	elseif(!empty($variant_id))
	{
		$simpla->variants->update_variant($variant_id, $variant);
	}
	// �������� ������
	if(isset($xml_product->���������������->����������������))
	{
		foreach ($xml_product->���������������->���������������� as $xml_option)
		{
			if(isset($_SESSION['features_mapping'][strval($xml_option->��)]))
			{
				$feature_id = $_SESSION['features_mapping'][strval($xml_option->��)];
				if(isset($category_id) && !empty($feature_id))
				{
					$simpla->features->add_feature_category($feature_id, $category_id);
					$values = array();
					foreach($xml_option->�������� as $xml_value)
					{
						if(isset($_SESSION['features_values'][strval($xml_value)]))
							$values[] = strval($_SESSION['features_values'][strval($xml_value)]);
						else
							$values[] = strval($xml_value);
					}
					$simpla->features->update_option($product_id, $feature_id, implode(' ,', $values));
				}
			}
			// ���� �������� ��������� ��������� ������
			elseif(isset($_SESSION['brand_option_id']) && !empty($xml_option->��������))
			{
				$brand_name = strval($xml_option->��������);
				// ������� �����
				// ������ ��� �� �����
				$simpla->db->query('SELECT id FROM __brands WHERE name=?', $brand_name);
				if(!$brand_id = $simpla->db->result('id'))
					// ��������, ���� �� ������
					$brand_id = $simpla->brands->add_brand(array('name'=>$brand_name, 'meta_title'=>$brand_name, 'meta_keywords'=>$brand_name, 'meta_description'=>$brand_name, 'url'=>translit($brand_name)));	
				if(!empty($brand_id))
					$simpla->products->update_product($product_id, array('brand_id'=>$brand_id));
			}
		}		
	}
	
	
	// ���� ����� - ������� ������� ��� ���� �����
	if($xml_product->������ == '������')
	{
		$simpla->variants->delete_variant($variant_id);
		$simpla->db->query('SELECT count(id) as variants_num FROM __variants WHERE product_id=?', $product_id);
		if($simpla->db->result('variants_num') == 0)
			$simpla->products->delete_product($product_id);
	}
}
function import_variant($xml_variant)
{
	global $simpla;
	global $dir;
	$variant = new stdClass;
	//  Id ������ � �������� (���� ����) �� 1�
	@list($product_1c_id, $variant_1c_id) = explode('#', $xml_variant->��);
	if(empty($variant_1c_id))
		$variant_1c_id = '';
	if(empty($product_1c_id))
		return false;
	$simpla->db->query('SELECT v.id FROM __variants v WHERE v.external_id=? AND product_id=(SELECT p.id FROM __products p WHERE p.external_id=? LIMIT 1)', $variant_1c_id, $product_1c_id);
	$variant_id = $simpla->db->result('id');
	
	$simpla->db->query('SELECT p.id FROM __products p WHERE p.external_id=?', $product_1c_id);
	$variant->external_id = $variant_1c_id;
	$variant->product_id = $simpla->db->result('id');
	if(empty($variant->product_id))
		return false;
	$variant->price = $xml_variant->����->����->�������������;	
	
	if(isset($xml_variant->��������������������->��������������������))
	foreach($xml_variant->��������������������->�������������������� as $xml_property)
		$values[] = $xml_property->��������;
	if(!empty($values))
		$variant->name = implode(', ', $values);
	$sku = (string)$xml_variant->�������;
	if(!empty($sku))
		$variant->sku = $sku;
	
	
	// ������������ ���� �� ������ 1� � ������� ������ ��������
	if(!empty($xml_variant->����->����->������))
	{
		// ���� ������ �� ����
		$simpla->db->query("SELECT id, rate_from, rate_to FROM __currencies WHERE code like ?", $xml_variant->����->����->������);
		$variant_currency = $simpla->db->result();
		// ���� �� ����� - ���� �� �����������
		if(empty($variant_currency))
		{
			$simpla->db->query("SELECT id, rate_from, rate_to FROM __currencies WHERE sign like ?", $xml_variant->����->����->������);
			$variant_currency = $simpla->db->result();
		}
		// ���� ����� ������ - ������������ �� ��� � �������
		if($variant_currency && $variant_currency->rate_from>0 && $variant_currency->rate_to>0)
		{
			$variant->price = floatval($variant->price)*$variant_currency->rate_to/$variant_currency->rate_from;
		}	
	}
	
	$variant->stock = $xml_variant->����������;
	if(empty($variant_id))
		$simpla->variants->add_variant($variant);
	else	
		$simpla->variants->update_variant($variant_id, $variant);
}
function translit($text)
{
	$ru = explode('-', "�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�-�"); 
	$en = explode('-', "A-a-B-b-V-v-G-g-G-g-D-d-E-e-E-e-E-e-ZH-zh-Z-z-I-i-I-i-I-i-J-j-K-k-L-l-M-m-N-n-O-o-P-p-R-r-S-s-T-t-U-u-F-f-H-h-TS-ts-CH-ch-SH-sh-SCH-sch---Y-y---E-e-YU-yu-YA-ya");
 	$res = str_replace($ru, $en, $text);
	$res = preg_replace("/[\s]+/ui", '-', $res);
	$res = strtolower(preg_replace("/[^0-9a-z�-�\-]+/ui", '', $res));
 	
    return $res;  
}