<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# xAPI Reporting Sistemi

Bu sistem, Articulate Storyline 360 ile oluşturulan xAPI içeriklerinin entegrasyonunu ve kullanıcı bazlı raporlamasını sağlar.

## Storyline 360 xAPI İçeriğinin Sisteme Entegrasyonu ve Kullanıcı Bazlı Çalıştırılması

### İçerik Yükleme Süreci

Storyline 360'tan xAPI formatında dışa aktarılan ZIP dosyası sisteme yüklendiğinde:

1. ZIP dosyası `storage/app/public/xapi-content/` dizinine açılır.
2. Sistem, içerik klasöründe `tincan.xml` dosyasını arar.
3. Bu dosya, içeriğin yapısını, aktivitelerini ve başlatma URL'sini tanımlar.
4. `tincan.xml` dosyasından içerik ID'si, adı ve başlatma URL'si (genellikle `index_lms.html`) çıkarılır.
5. Bu bilgiler veritabanına kaydedilir.

### Kullanıcı İçin Actor Bilgilerinin Hazırlanması

Bir kullanıcı içeriği başlattığında, sistem şu adımları izler:

1. İçerik bilgilerini veritabanından alır.
2. Kullanıcının adı ve e-posta adresi kullanılarak bir xAPI Actor nesnesi oluşturur.
3. Benzersiz bir oturum ID'si (registration) oluşturur.
4. LRS (Learning Record Store) endpoint bilgilerini hazırlar.
5. Bu bilgileri, içeriği başlatmak için kullanılan URL'ye parametre olarak ekler.

### İçeriğin Başlatılması

Kullanıcı için içerik başlatılırken:

```json
{
  "name": "Kullanıcı Adı",
  "mbox": "mailto:kullanici@ornek.com",
  "objectType": "Agent"
}
```

1. Benzersiz bir oturum ID'si (registration) oluşturulur - bu, kullanıcının bu içerikle olan her oturumunu benzersiz şekilde tanımlar.
2. LRS (Learning Record Store) endpoint bilgileri hazırlanır - bu, xAPI ifadelerinin (statements) nereye gönderileceğini belirtir.
3. Bu bilgiler, içeriği başlatmak için kullanılan URL'ye parametre olarak eklenir.

### İçeriğin Çalıştırılması

İçerik başlatıldığında, `content_launch.blade.php` şablonu kullanılarak bir sayfa oluşturulur. Bu şablon:

1. JavaScript kullanarak xAPI başlatma parametrelerini hazırlar.
2. Bu parametreleri URL'ye ekleyerek içeriği başlatır.
3. İçerik, kullanıcıya özel actor bilgileriyle yüklenir.

Örnek başlatma kodu:

```javascript
var launchParams = {
    endpoint: "https://xapi-reporting.example.com/xapi/statements",
    auth: "Basic dXNlcm5hbWU6cGFzc3dvcmQ=",
    actor: {
        "name": "Kullanıcı Adı",
        "mbox": "mailto:kullanici@ornek.com",
        "objectType": "Agent"
    },
    registration: "550e8400-e29b-41d4-a716-446655440000",
    activity_id: "urn:articulate:storyline:6Lmotbxq1E0"
};

// İçeriği başlat
window.location.href = "content-serve/testus/unsuz/index_lms.html?" + 
    Object.keys(launchParams).map(function(key) {
        return key + '=' + encodeURIComponent(
            key === 'actor' ? JSON.stringify(launchParams[key]) : launchParams[key]
        );
    }).join('&');
```

### xAPI İfadelerinin İşlenmesi

İçerik çalıştırıldığında:

1. Storyline içeriği, kullanıcının etkileşimlerini xAPI ifadeleri (statements) olarak kaydeder.
2. Bu ifadeler, sistemin `/xapi/statements` endpoint'ine gönderilir.
3. `XapiController` sınıfı bu ifadeleri alır, işler ve veritabanına kaydeder.
4. Her ifade, hangi kullanıcının (actor) hangi içerikle (activity) ne tür bir etkileşimde (verb) bulunduğunu ve sonucu (result) kaydeder.

Örnek bir xAPI ifadesi:

```json
{
  "id": "12345678-1234-5678-1234-567812345678",
  "actor": {
    "name": "Kullanıcı Adı",
    "mbox": "mailto:kullanici@ornek.com",
    "objectType": "Agent"
  },
  "verb": {
    "id": "http://adlnet.gov/expapi/verbs/answered",
    "display": {
      "en-US": "answered"
    }
  },
  "object": {
    "id": "urn:articulate:storyline:6Lmotbxq1E0/6ehF2ezRBaq/5j5moqR8HE4",
    "definition": {
      "name": {
        "en-US": "Drag and Drop"
      },
      "description": {
        "en-US": "Drag and Drop"
      },
      "type": "http://adlnet.gov/expapi/activities/cmi.interaction",
      "interactionType": "matching"
    },
    "objectType": "Activity"
  },
  "result": {
    "score": {
      "scaled": 1,
      "raw": 8,
      "min": 0,
      "max": 8
    },
    "success": true,
    "completion": true,
    "response": "statement_5c4JmWbDafD[.]choice_5afsjw5M7TK[,]statement_6lMxLlA8FvZ[.]choice_606Gyw3DYiH[,]statement_6gsF4bEJukS[.]choice_5tHy6cAu73H[,]statement_6jd1DuQYL02[.]choice_6bbURi79Nvx[,]statement_5twiR2Jds3H[.]choice_600Vg3IUMUn[,]statement_6H3OUCuUbKd[.]choice_6mIjjWMqPys[,]statement_64XYvCHousm[.]choice_6oFy0nSd6Af[,]statement_6d8koSAiseG[.]choice_6clsleg5qNO"
  },
  "context": {
    "registration": "550e8400-e29b-41d4-a716-446655440000",
    "contextActivities": {
      "parent": [
        {
          "id": "urn:articulate:storyline:6Lmotbxq1E0/6ehF2ezRBaq",
          "objectType": "Activity"
        }
      ],
      "grouping": [
        {
          "id": "urn:articulate:storyline:6Lmotbxq1E0",
          "objectType": "Activity"
        }
      ]
    }
  },
  "timestamp": "2023-11-15T10:30:45Z"
}
```

### Raporlama

Sistem, kaydedilen xAPI ifadelerini kullanarak çeşitli raporlar oluşturur:

1. **Kullanıcı Raporları:** Her kullanıcının hangi içeriklerle etkileşimde bulunduğunu, tamamlama durumunu ve başarı oranını gösterir.
2. **İçerik Raporları:** Her içeriğin kaç kullanıcı tarafından kullanıldığını, ortalama tamamlama oranını ve başarı durumunu gösterir.
3. **Detaylı Etkileşim Raporları:** Kullanıcıların içerikteki belirli sorulara verdikleri yanıtları ve başarı durumlarını gösterir.

### tincan.xml Dosyası

Storyline 360'tan dışa aktarılan içeriklerin kök dizininde bulunan `tincan.xml` dosyası, içeriğin yapısını tanımlar. Bu dosya:

1. İçeriğin ana ID'sini ve adını belirtir.
2. İçeriğin başlatılması gereken dosyayı (genellikle `index_lms.html`) tanımlar.
3. İçerikteki tüm aktiviteleri (ekranlar, sorular, etkileşimler) listeler.
4. Her aktivitenin ID'sini, tipini ve adını belirtir.

Örnek bir `tincan.xml` dosyası:

```xml
<?xml version="1.0" encoding="utf-8"?>
<tincan xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://projecttincan.com/tincan.xsd">
    <activities>
        <activity id="urn:articulate:storyline:6Lmotbxq1E0" type="http://adlnet.gov/expapi/activities/course">
            <name lang="und">Ünsüz Harfler - İnteraktif Çalışmalar</name>
            <description lang="und" />
            <launch lang="und">index_lms.html</launch>
        </activity>
        <activity id="urn:articulate:storyline:6Lmotbxq1E0/6UuSomivp4H" type="http://adlnet.gov/expapi/activities/objective">
            <name lang="und">Ünsüz Harfler - İnteraktif Çalışmalar</name>
            <description lang="und">Ünsüz Harfler - İnteraktif Çalışmalar</description>
        </activity>
        <!-- Diğer aktiviteler -->
    </activities>
</tincan>
```

Bu entegrasyon süreci sayesinde:

1. Her kullanıcı için özelleştirilmiş bir öğrenme deneyimi sağlanır.
2. Kullanıcının içerikle olan tüm etkileşimleri kaydedilir.
3. Kullanıcı, içeriği bıraktığı yerden devam edebilir.
4. Yöneticiler, kullanıcıların içerikle olan etkileşimlerini detaylı olarak raporlayabilir.
