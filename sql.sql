UPDATE `prefix_products` i INNER JOIN (
	SELECT p.id, v.qu, ROUND(v.price*(1+b.margin/100)) price FROM `prefix_products` p
	INNER JOIN `prefix_products_topics` t ON t.id=p.top
	INNER JOIN `prefix_products_topics` b ON b.id=t.top
	INNER JOIN `prefix_vendor_products` v ON v.product_id=p.id AND v.price=IFNULL(
		(SELECT min(w.price) FROM `prefix_vendor_products` w WHERE w.product_id=p.id AND w.qu>3),
		IFNULL(
			(SELECT min(w.price) FROM `prefix_vendor_products` w WHERE w.product_id=p.id AND w.qu>0),
			(SELECT min(w.price) FROM `prefix_vendor_products` w WHERE w.product_id=p.id AND w.qu=0)
		)
	) WHERE p.fixed_price="N" GROUP BY v.product_id
) j ON (j.id=i.id) SET i.price=j.price, i.remain=j.qu, i.modified=NOW();