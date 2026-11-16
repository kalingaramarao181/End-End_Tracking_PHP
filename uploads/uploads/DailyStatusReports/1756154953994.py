def calculate_total(order, menu):
    total = 0
    # TODO: Loop over order and calculate total using item price and quantity
    for item_id, quantity in order:
        if item_id in menu:
            price = menu[item_id]['price']
            total += quantity*price
    return round(total,2)
