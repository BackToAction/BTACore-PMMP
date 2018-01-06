
namespace BTA/API;

class Health {



    // Health API
    public function getPlayerHealth($player){
        $i = $this->getPlayerDatabase($player);
        $result = $i->get("health");
        return $result;
    }
    public function setNewHealth($level){
        $mtt = 3773;
        if($level > 0 && $level <= 200){
            return $level + $mtt;
        }else{
            return 0;
        }
    }
    public function updateHealth($player){
        $i = $this->getPlayerDatabase($player);
        $ll = $this->getPlayerLevel($player);
        $h = $this->getPlayerHealth($player);
        $mxh = $i->get("maxhealth");
        if($h >= $mxh){
            $i->set("health", $mxh);
            $i->save();
        }
    }
    public function addHealth($player, $addhealth){
        $i = $this->getPlayerDatabase($player);
        $mxh = $i->get("maxhealth");
        $h = $i->get("health");
        $cal = $h + $addhealth;
        if($addhealth > $mxh || $cal > $mxh){
            $this->updateHealth($player);
        }else{
            $i->set("health", $h + $mxh);
            $i->save();
        }
    }
    public function minusHealth($player, $dmg){
        $i = $this->getPlayerDatabase($player);
        $maxh = $i->get("maxhealth");
        $h = $i->get("health");
        if($h >= 1){
            if($dmg > $maxh || $dmg > $h){
                $i->set("health", 0);
                $i->save();
            }elseif($dmg < $h){
                $i->set("health", $h - $dmg);
                $i->save();
            }
        }
    }
          
          
          }
