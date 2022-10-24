todo
---


- unit testlerin yazılması - pest
    - rule validation testleri
- dökümanlar



todo v2
---
A

backlog
---
- config e aranacak model klasörü eklenecek ?? AStart a mı eklemek lazım?
- abac rule'u eloquent'a - dönüştüren builder ?? şimdilik scope içinden yapıldı

-----

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
