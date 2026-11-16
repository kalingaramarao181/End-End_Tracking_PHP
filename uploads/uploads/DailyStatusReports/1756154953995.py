from datetime import datetime

def save_order(order, total, filename, menu):
    # TODO: Write order details and total bill to file with timestamp
    try:
        with open(filename, 'a', encoding='utf-8') as file:
            # Writes date and time 
            time = datetime.now().strftime("%m-%d-%Y %H:%M:%S") 
               # %Y - year full, %m - month as number, %d - day of month
            file.write(f"Order placed on: {time}\n")

            # Writes header for orders
            file.write(f"{'ID':<10}{'Name':<20}{'Quantity':<10}{'Price':>10}\n")
            file.write("-" * 60 + "\n")

            # Write each item in an order
            for item_id, quantity in order: # Order List example: [('3',4), ('4',1)]: Order is a list containing tuples ('3', 4) with '3' as item_id and 4 as quantity
                if item_id in menu:
                    name = menu[item_id]['name'] # checks 'name' of nested dictionary from the item-id in that nested dictionary, the item_id is extracted from order list 
                    unit_price = menu[item_id]["price"]
                    file.write(f"{item_id:<10}{name:<20}{quantity:<10}₹{unit_price:>9.2f}\n")

            # Write total
            file.write("-" * 60 + "\n")
            file.write(f"{'Total':<40}₹{total:>9.2f}\n") 
            # here, < refers left alignment(across the specified character length- 40), > represents right alignment, .2f represents formatting a number to 2 decimal places
            file.write("=" * 60 + "\n")

    except Exception as e:
        print(f"Failed to save order: {e}")
    pass


