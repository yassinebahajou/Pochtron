<?php
//Application JSON
header('Content-type: application/json'); 

//Codes Erreurs
define('AJAX_OK',0);
define('AJAX_UNKNOW_FAIL',1);
define('AJAX_NOT_IMPLEMENTED',2);
define('UNDEFINED_ACTION',100);
define('INVALID_ACTION',101);
define('UNDEFINED_ID',200);
define('INVALID_ID',201);
define('EMPTY_ORDER',300);
define('INVALID_ORDER',301);

//Initialisations
$erreur=-1;
$reponse='';
date_default_timezone_set('Europe/Paris');
$return_array=array();

//Include pour l'objet $bdd notamment
include_once 'inclus/tete.inc.php';

/*/////////////////////////////FONCTIONS/////////////////////////////*/
//Conversion de la chaîne de commande en un tableau
function parse_order($string_order)
{
	$array_temp = explode(',',$string_order);
	$array_order = array();
	
	for ($i=1;$i<count($array_temp)-1;$i++)
	{
		$array_order[$i] = explode(':',$array_temp[$i]);
	}
	
	return $array_order;
}

//Prendre une nouvelle commande
function new_order()
{
	global $return_array;
	global $erreur;
	global $reponse;
	global $sql;
	
	if (!isset($_GET['id']))
	{
		$erreur=UNDEFINED_ID;
		$reponse="Id indéfini";
		return;
	}
	
	$_GET['id'] = intval($_GET['id']); // Empêche l'injection SQL
	$sql->rek( 'SELECT * FROM clients WHERE id=\''.$_GET['id'].'\'');//Requète
	
	if ($sql->nbrlignes() != 1)
	{
		$erreur=INVALID_ID;
		$reponse="Id invalide";
	}
	else
	{
		$eleve = $sql->fetch();
		
		if (!isset($_GET['consom']))
		{
			$erreur=EMPTY_ORDER;
			$reponse="Commande vide";
			return;
		}
		
		$order = parse_order(htmlspecialchars($_GET['consom'], ENT_QUOTES, 'UTF-8'));
		$new_solde=$eleve['solde'];
		$new_nb_consos = $eleve['nb_consos'];
		$new_litres_bus = $eleve['litres_bus'];
		
		if (count($order) < 1)
		{
			$erreur=INVALID_ORDER;
			$reponse="Commande invalide";
			return;
		}

		array_multisort($order, SORT_ASC);
		
		$rek_in='';
		for ($i=0;$i<count($order);$i++) $rek_in = $rek_in.$order[$i][0].',';
		$rek_in = substr($rek_in, 0, strlen($rek_in)-1);
		
		$i=0;
		
		$sql->rek( 'SELECT * FROM produits WHERE id IN ('.$rek_in.') ORDER BY id ASC');
		while($products = $sql->fetch())
		{
			if ($products['id']==$order[($i)][0])
			{
				$new_solde -= $products['prix']*$order[$i][1];
				$new_nb_consos += $order[$i][1];
				$new_litres_bus += $products['vol']*$order[$i][1];
				
				$return_array['id']=$eleve['id'];
				$return_array['solde']=$new_solde;
				$return_array['nb_consos']=$new_nb_consos;
				$return_array['litres_bus']=$new_litres_bus;
				
				$sql->rek('UPDATE produits SET qtt_reserve=\''.($products['qtt_reserve']-$order[$i][1]).'\', ventes=\''.($products['ventes']+$order[$i][1]).'\' WHERE id=\''.$products['id'].'\'', false);
				$sql->rek('INSERT INTO commandes (id_user, timestamp, id_produit, qtte_produit) VALUES (\''.$_GET['id'].'\',\''.date("Y-m-d H:i:s").'\',\''.$products['id'].'\',\''.$order[$i][1].'\')', false);
			}
			$i++;
		}

		$sql->rek( 'UPDATE clients SET solde=\''.($new_solde).'\', litres_bus=\''.($new_litres_bus).'\', nb_consos=\''.($new_nb_consos).'\' WHERE id=\''.$_GET['id'].'\'');
		
		
		$erreur = AJAX_OK;
		$reponse = "Commande de ".$eleve['prenom']." ".$eleve['nom']." passée avec succès. Nouveau solde : ".($new_solde);
		
		return $return_array;
	}
}

//Ajouter du liquide à un élève
function add_cash()
{
	global $erreur;
	global $reponse;
	
	$erreur = AJAX_NOT_IMPLEMENTED;
	$reponse = "Le solde de l'élève a bien été augmenté";
}

//Ajouter du liquide à un élève
function extern_order()
{
	global $erreur;
	global $reponse;
	static $return_array;
	
	$erreur = AJAX_NOT_IMPLEMENTED;
	$reponse = "Non implémenté";
}

//Annuler une commande
function cancel()
{
	global $return_array;
	global $erreur;
	global $reponse;
	global $sql;
	
	if (!isset($_GET['id']))
	{
		$erreur=UNDEFINED_ID;
		$reponse="Id indéfini";
		return;
	}
	else
		$_GET['id'] = intval($_GET['id']);
	
	
	
	$sql->rek( 'SELECT c.id_user AS client_id, c.id_produit AS produit_id, c.qtte_produit, p.qtt_reserve AS produit_qtte_reserve, p.ventes AS produit_ventes, p.prix AS produit_prix, p.vol AS produit_vol, e.solde AS client_solde, e.litres_bus AS client_litres_bus, e.nb_consos AS client_nb_consos, e.prenom AS client_prenom, e.nom AS client_nom FROM commandes c, produits p, clients e WHERE c.id=\''.$_GET['id'].'\' AND p.id=c.id_produit AND e.id=c.id_user');//Requète

	if ($sql->nbrlignes() != 1)
	{
		$erreur=INVALID_ID;
		$reponse="Id invalide";
	}
	else
	{
		$commande = $sql->fetch();
				
		$new_solde=$commande['client_solde'] + $commande['qtte_produit']*$commande['produit_prix'];
		$new_nb_consos = $commande['client_nb_consos'] - $commande['qtte_produit'];
		$new_litres_bus = $commande['client_litres_bus'] - $commande['qtte_produit']*$commande['produit_vol'];
		$sql->rek( 'UPDATE clients SET solde=\''.($new_solde).'\', litres_bus=\''.($new_litres_bus).'\', nb_consos=\''.($new_nb_consos).'\' WHERE id=\''.$commande['client_id'].'\'');
		
		$new_ventes=$commande['produit_ventes'] - $commande['qtte_produit'];
		$new_qtte_reserve = $commande['produit_qtte_reserve'] + $commande['qtte_produit'];
		$sql->rek('UPDATE produits SET qtt_reserve=\''.$new_qtte_reserve.'\', ventes=\''.$new_ventes.'\' WHERE id=\''.$commande['produit_id'].'\'', false);
		
		$sql->rek('DELETE FROM commandes WHERE id=\''.$_GET['id'].'\'');
		
		$return_array['id']=$commande['client_id'];
		$return_array['solde']=$new_solde;
		$return_array['nb_consos']=$new_nb_consos;
		$return_array['litres_bus']=$new_litres_bus;
		
		$erreur = AJAX_OK;
		$reponse = 'La commande de '.$commande['client_prenom'].' '.$commande['client_nom'].' a été annulée avec succès';
	}	
}

$alias = array();
/*/////////////////////////////CORPS/////////////////////////////*/
if (isset($_GET['action']))
{
	switch ($_GET['action']) 
	{
		case "order":
			new_order();
			break;
		case "add_cash":
			add_cash();
			break;
		case "cancel":
			cancel();
			break;
		case "extern":
			extern_order();
			break;
		default:
		   $erreur = INVALID_ACTION;
		   $reponse = "Action incorrecte";
	}
}
else
{
	$erreur = UNDEFINED_ACTION;
	$reponse = "Action indéfinie";
}

?>
{
	<?php if (isset($return_array['id'])) 
	{
		foreach($return_array as $key => $value)
		{ ?>"<?php echo $key; ?>":"<?php echo $value; ?>", 
	<?php
		}
	}
	?>"code_erreur": "<?php echo $erreur; ?>",
	"reponse": "<?php echo $reponse;?>"
}                                                         
