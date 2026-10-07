<?php
function migrateOrganization():void {
 $columns=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?array_column(query('PRAGMA table_info(eval_groups)')->fetchAll(),'name'):array_column(query('SHOW COLUMNS FROM eval_groups')->fetchAll(),'Field');
 if(!in_array('grade_level',$columns,true)){
 db()->exec('ALTER TABLE eval_groups ADD COLUMN grade_level INTEGER NOT NULL DEFAULT 0');
 foreach(query('SELECT id,name FROM eval_groups')->fetchAll() as $g){if(preg_match('/^([1-5])\s*°/u',$g['name'],$match))query('UPDATE eval_groups SET grade_level=? WHERE id=?',[(int)$match[1],$g['id']]);}
 }
 db()->exec('CREATE TABLE IF NOT EXISTS eval_material_files(material_id INTEGER PRIMARY KEY,path VARCHAR(200) NOT NULL,original_name VARCHAR(200) NOT NULL,mime VARCHAR(100) NOT NULL,FOREIGN KEY(material_id) REFERENCES eval_materials(id))');
 query("UPDATE eval_materials SET status='publicado',observation=NULL WHERE status<>'publicado'");
}
migrateOrganization();
