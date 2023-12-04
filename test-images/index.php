<?php

if (count($_GET) > 0) { {
    if (array_key_exists("file", $_GET)) {
      $f = $_GET["file"];
      $mime = mime_content_type($f);
      if (strpos($mime, "text") === false) {
        header("Location: http://localhost:8888/$f");
      } else {
        header("Content-Type: text/plain; charset=utf-8");
        readfile($f);
      }
    }
  }
  return;
}

$CONFIG_FILE_JSON = ".immagio.json";

if (array_key_exists("command", $_POST) && strcmp($_POST["command"], "save") == 0) {
  if (file_put_contents($CONFIG_FILE_JSON, $_POST['data'])) {
    echo "Salvato con successo\n";
  } else {
    echo "Errore\n";
  }
}

if (array_key_exists("command", $_POST) && strcmp($_POST["command"], "apply") == 0) {
  $data = json_decode($_POST['data']);

  $bad_dirs = [];
  foreach ($data->etichette as $dir) {
    $e = file_exists($dir);
    if (($e && is_dir($dir)) || (!$e && mkdir($dir))) {
      echo "Creazione di '$dir' : SUCCESSO\n";
    } else {
      echo "Creazione di '$dir' : ERRORE\n";
      $bad_dirs[] = $dir;
    }
  }
  echo "\n";

  $bad_files = array_filter($data->associazioni, function ($i) use ($bad_dirs) {
    return in_array($i->label, $bad_dirs);
  });
  $associazioni = array_filter($data->associazioni, function ($i) use ($bad_dirs) {
    return !in_array($i->label, $bad_dirs);
  });

  foreach ($associazioni as $o) {
    $from = $o->path;
    $to = $o->label . '/' . $o->path;
    $e = file_exists($to);
    if (!$e && rename($from, $to)) {
      echo "Spostamento di '$from' in '$to' : SUCCESSO\n";
    } else {
      $bad_files[] = $o;
      $bad_dirs[] = $o->label;
      echo "Spostamento di '$from' in '$to' : " . ($e ? "FILE GIÀ ESISTENTE" : "ERRORE") . "\n";
    }
  }

  $associazioni = (object)["etichette" => array_unique($bad_dirs), "associazioni" => $bad_files];

  if (file_put_contents($CONFIG_FILE_JSON, json_encode($associazioni))) {
    echo "Salvato con successo\n";
  } else {
    echo "Errore\n";
  }
}

if (count($_POST) != 0) {
  die();
}

$script_file = str_replace(__DIR__ . "/", "", __FILE__);
$files = array_values(array_filter(glob('*'), function ($f) use ($script_file) {
  return !is_dir($f) && $f != $script_file;
}));

$etichette = [];
$associazioni = [];

if (file_exists($CONFIG_FILE_JSON)) {
  $conf = json_decode(file_get_contents($CONFIG_FILE_JSON));
  $etichette = $conf->etichette;
  $associazioni = array_values(array_filter($conf->associazioni, function ($i) use ($files) {
    return in_array($i->path, $files);
  }));
}

?>

<!DOCTYPE html>
<html>

<head>
  <script>
    var currentindex = 0;
    var images;
    var current_image;
    var newdir;
    var dirs;
    var etichette;

    function main() {
      newdir = document.getElementById("new-directory");
      dirs = document.getElementById("target-directories");

      current_image = document.getElementById("current-image");
      images = document.getElementById("images");
      etichette = {};

      var associazioni = {};

      function create_etichetta(nome) {
        var e = make_etichetta(nome);
        associazioni[nome] = ({
          radio: e.radio,
          text: e.text
        });
        dirs.append(e.row);
      };

      <?php echo json_encode($etichette); ?>.forEach(create_etichetta);

      function create_miniatura(nome) {
        images.append(make_miniatura(images.children.length, nome, nome));
      };

      <?php echo json_encode($files); ?>.forEach(create_miniatura);

      function associa_categorie_a_immagini(o) {
        etichette[o.path] = associazioni[o.label];
      };
      <?php echo json_encode($associazioni); ?>.forEach(associa_categorie_a_immagini);
      set_current_image(0);
    }

    function select_image(image, border, check) {
      image.style.border = border;
      if (etichette.hasOwnProperty(image.alt)) {
        etichette[image.alt].radio.checked = check;
      }
    }

    function set_current_image(i) {
      select_image(images.children[currentindex], "none", false);

      currentindex = i;

      var image = images.children[currentindex];
      select_image(image, "thick solid #6666FF", true);

      current_image.src = image.src;
      current_image.alt = image.alt;

      image.scrollIntoView();

    }

    function make_miniatura(i, path, alt) {
      var img = document.createElement("img");
      img.src = path;
      img.name = "miniatura";
      img.alt = alt;
      // img.onclick = function() {
      //   set_current_image(i);
      // }
      var a = document.createElement("A");
      a.href = path;
      if(alt == "r2.hs"){
        a.href = "view-source:http://localhost:8888/" + path
      }
      a.target="fullpage";
      a.appendChild(img);
      return a;
    }

    function make_etichetta(label_name) {
      var radio = document.createElement("input");
      radio.classList.add("radio");
      radio.type = "radio";
      radio.name = "label_radio";
      var text = document.createElement("input");
      text.classList.add("text");
      text.type = "text";
      text.name = "label_text";
      text.value = label_name;
      var row = document.createElement("div");
      row.append(radio);
      row.append(text);
      radio.onclick = function() {
        var b = !etichette.hasOwnProperty(current_image.alt);
        etichette[current_image.alt] = {
          radio: radio,
          text: text
        };

        if (b) {
          set_current_image((currentindex + 1) % images.children.length);
        }
      };
      return ({
        row: row,
        radio: radio,
        text: text
      });
    }

    function add_target_directory() {
      const label = newdir.value;
      const duplicates = Object.values(dirs.children)
        .map(i => i.children[0])
        .filter(i => i.value == label)
        .length;
      if (label != "" && duplicates == 0) {
        dirs.append(make_etichetta(label).row);
        newdir.value = "";
      }
    }

    function get_labels_text() {
      return Object.values(document.getElementsByName("label_text")).map(i => i.value);
    }

    function get_pair_image_label() {
      return Object.values(images.children)
        .map(i => i.alt)
        .filter(i => etichette.hasOwnProperty(i))
        .map(i => ({
          path: i,
          label: etichette[i].text.value
        }));
    }

    function call_php(data, func) {
      const xmlhttp = new XMLHttpRequest();
      xmlhttp.open("POST", "index.php", true);
      xmlhttp.onload = func;
      xmlhttp.send(data);
    }

    function save() {
      var data = new FormData();
      data.append("command", "save");
      data.append("data", JSON.stringify({
        etichette: get_labels_text(),
        associazioni: get_pair_image_label()
      }));
      call_php(data, function() {
        alert(this.responseText);
      });
    }

    function apply() {
      var data = new FormData();
      data.append("command", "apply");
      data.append("data", JSON.stringify({
        etichette: get_labels_text(),
        associazioni: get_pair_image_label()
      }));
      call_php(data, function() {
        alert(this.responseText);
        window.location.reload(false);
      });
    }
  </script>

  <style>
    html {
      height: 100%;
    }

    body {
      height: 100%;
      margin: 0px;
      display: grid;
      grid-template-columns: min-content auto min-content;
      grid-template-rows: 100%;
      justify-items: center;
      align-items: center;
    }

    #current-image {
      max-width: 90%;
      max-height: 90%;
      height: 90%;
      width: 90%;
    }

    #images {
      margin: 20px;
      overflow: scroll;
      height: 90%;
      display: flex;
      flex-direction: column;
    }

    #images img {
      width: 160px;
      margin: 10px;
      margin-right: 20px;
    }

    #controls {
      margin: 20px;
      height: 90%;
      display: grid;
      grid-template-rows: min-content min-content min-content auto min-content;
      grid-gap: 10px;
    }

    #target-directories {
      overflow: scroll;
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    #target-directories div {
      display: grid;
      grid-template-columns: min-content auto;
    }

    #target-directories .text {
      font-size: 22px;
      width: 90%;
    }

    #target-directories .radio {
      height: 20px;
      width: 20px;
    }
  </style>
</head>

<!-- <body onload="main()"> -->

<body>
  <div id="images">
    <?php
    foreach ($files as $i => $f) {
      echo "<a target=fullpage href='./?file=$f'>
      <img src=$f alt=$f onclick='this.scrollIntoView();'>
      </a>\n";
    }
    ?>
  </div>
    <iframe name=fullpage id="current-image" alt="IMMAGINE" <?php echo (count($files) > 0 ? ("src='./?file=" . $files[0]) . "'" : "") ?>" ></iframe>
  <div id="controls">
    <button onclick="save()">Salva</button>
    <button onclick="add_target_directory()">Nuova directory</button>
    <input id="new-directory" type="text">
    <div id="target-directories">
      <?php
      foreach ($etichette as $i => $e) {
        echo "<div>
        <input class=radio type=radio name=label_radio>
        <input class=text  type=text  name=label_text  value='$e'>
        </div>\n";
      }
      ?>
    </div>
    <button onclick="apply()">Applica modifiche</button>
  </div>
</body>

</html>
