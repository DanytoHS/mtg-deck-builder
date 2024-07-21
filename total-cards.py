import requests

response = requests.get("https://api.scryfall.com/cards/search?q=%2A")
data = response.json()

# Imprime la respuesta completa para ver su estructura
print(data)

# Verifica si 'total_cards' está en la respuesta
if 'total_cards' in data:
    total_cards = data['total_cards']
    print(f"Total de cartas: {total_cards}")
else:
    print("El campo 'total_cards' no está presente en la respuesta.")