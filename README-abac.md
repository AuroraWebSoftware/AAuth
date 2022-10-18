todo
---

- config e aranacak model klasörü eklenecek ??
- abac rule'u eloquent'a - dönüştüren builder ?? şimdilik scope içinden yapıldı
- unit testlerin yazılması - pest
  - rule validation testleri
- dökümanlar

-

```json
{
    "&&": [
        {
            "==": [
                "$attribute",
                "asd"
            ]
        },
        {
            "==": [
                "$attribute",
                "asd"
            ]
        },
        {
            "||": [
                {
                    "==": [
                        "$attribute",
                        "asd"
                    ]
                },
                {
                    "==": [
                        "$attribute",
                        "asd"
                    ]
                }
            ]
        }
    ]
}
```

```php

[
    "&&" => [
                ["==" => [ "attribute" => "$attribute", "value" => "asasd"]]
            ],
            ["||" =>
                [
                    ["==" => [ "attribute" => "$attribute", "value" => "asasd"]],
                ]
            ]
    ]
]

```
