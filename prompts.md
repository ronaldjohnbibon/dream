# Bigasan Application — Codex Session Prompts

Use **one prompt per Codex session**.

Do not give Codex all sessions at once. Finish and verify the current session first, then start a new Codex session using the next prompt.

---

# SESSION 1 — Bigasan Foundation + Rice Inventory

We are building a Bigasan web application using the existing Laravel + Vue 3 + TypeScript + Inertia.js modular monolith starter.

For this session, only implement the Bigasan foundation and Rice Inventory.

## General Rules

* Follow the project's existing Laravel, Vue, TypeScript, Inertia, and Tailwind patterns.
* Use the existing modular structure.
* Make the code simpler and more like a human-coded application.
* Avoid overengineered logic and unnecessary abstractions.
* Keep the code as straight to the point as possible.
* Do not create unnecessary repositories, DTOs, interfaces, services, helpers, or base classes.
* Use services/actions only when genuinely needed.
* Reuse existing components and logic before creating new ones.
* Keep changes focused only on this session.
* Do not refactor unrelated code.
* Use TypeScript and avoid `any` where possible.
* Use Laravel validation and authorization.
* Use Inertia for normal forms and navigation.
* Do not create unnecessary APIs.
* Do not create Laravel tests.
* Do not create Vue tests.

## Implement

Create the Rice Products and Inventory module.

Rice Product fields:

* Name
* Brand
* Description
* Sack size
* Cost price
* Selling price
* Available stock
* Reserved stock
* Reorder level
* Status

Initial standard sack size:

* 25kg

Admin should be able to:

* View rice products
* Add rice
* Edit rice
* Activate/deactivate rice
* Adjust stock
* View available stock
* View low-stock products

Add inventory movement history.

Movement types:

* Stock In
* Order
* Cancellation
* Adjustment
* Damaged
* Returned

Record:

* Rice product
* Quantity
* Type
* Previous stock
* New stock
* Related order when applicable
* Notes
* User
* Date

Do not directly modify inventory without recording the corresponding stock movement.

Keep the UI clean and simple.

Only implement this session.

---

# SESSION 2 — Customer Profile

Continue the existing Bigasan application.

For this session, only implement customer-related functionality.

Follow the same coding rules from the previous session:

* Simple human-readable code.
* Avoid overengineering.
* Straight-to-the-point implementation.
* Reuse existing code.
* No unnecessary abstractions.
* No unrelated refactors.
* No Laravel tests.
* No Vue tests.

## Implement

Extend the existing User functionality to support Bigasan customers.

Customer information:

* Name
* Email
* Mobile number
* Complete address
* Delivery area
* Account status

Statuses:

* Good Standing
* Overdue
* Suspended

Admin should be able to:

* View customers
* Edit customer details
* Suspend customer
* Reactivate customer
* View basic customer summary

Customer summary:

* Total orders
* Completed pautang
* Active pautang
* On-time payments
* Late payments
* Outstanding balance
* Current points

For now, prepare the customer structure so later sessions can connect orders, payments, pautang, and points.

Do not implement those modules yet.

Only implement this session.

---

# SESSION 3 — Orders

Continue the existing Bigasan application.

For this session, only implement Orders.

Keep the implementation simple and consistent with existing patterns.

Do not create Laravel or Vue tests.

## Customer

Customer can:

* View available rice
* View selling price
* Select rice
* Select quantity
* Choose Cash or Pautang
* Select/enter delivery address
* Place order

## Order Fields

* Order number
* Customer
* Rice product
* Quantity
* Unit price
* Subtotal
* Points used
* Points discount
* Final amount
* Payment type
* Delivery address
* Delivery area
* Order date
* Delivery date
* Order status
* Payment status
* Notes

Points fields can remain unused until the Points session.

## Payment Types

* Cash
* Pautang

## Order Status

* Pending
* Confirmed
* Preparing
* Out for Delivery
* Delivered
* Completed
* Cancelled

## Payment Status

* Unpaid
* Partially Paid
* Paid
* Overdue

## Rules

A customer may only have:

* 1 active pautang
* Maximum 1 sack per pautang

If a customer still has an unpaid active pautang, they cannot create another pautang order.

Cash orders should still be allowed.

Suspended customers cannot create orders.

Connect orders properly with inventory.

Prevent orders when stock is insufficient.

Handle reservation/deduction/restoration of stock using the existing inventory movement implementation.

Do not implement installments, GCash payments, or points yet.

Only implement this session.

---

# SESSION 4 — Pautang / 2 Gives

Continue the Bigasan application.

For this session, implement only the Pautang and installment functionality.

Keep everything simple.

No Laravel tests.
No Vue tests.

## Default Rules

* Maximum 1 active pautang
* Maximum 1 sack
* 2 gives / installments
* 1 month total payment period
* 50% first installment
* 50% second installment

Example:

₱1,500 order:

* Give 1 = ₱750
* Give 2 = ₱750

Automatically create the two installments for an approved pautang order.

Each installment should contain:

* Order
* Installment number
* Amount due
* Due date
* Amount paid
* Remaining balance
* Status
* Paid date

Statuses:

* Pending
* Partially Paid
* Paid
* Overdue

Admin should be able to view:

* Active pautang
* Fully paid pautang
* Overdue pautang
* Customer
* Order amount
* Amount paid
* Remaining balance
* Next due date
* Days overdue

Customer should be able to see:

* Current pautang
* Total balance
* Amount paid
* Remaining balance
* Give 1
* Give 2
* Due dates
* Payment statuses

Keep this directly related to the order.

Do not build complicated lending/accounting architecture.

---

# SESSION 5 — GCash Payment + Verification

Continue the Bigasan application.

For this session, implement GCash payment submission and Admin verification.

No Laravel tests.
No Vue tests.

## Payment Method

GCash through QR code only.

Display the configured GCash QR code to customers.

Customer submits:

* Order
* Related installment if pautang
* Amount
* GCash reference number
* Payment screenshot
* Payment date

Statuses:

* Pending Verification
* Approved
* Rejected

Admin can:

* View pending payments
* View payment screenshot
* View reference number
* Approve
* Reject
* Add remarks

Important:

Submitting payment must NOT immediately update the customer's balance.

Only an Admin-approved payment can affect:

* Installment amount paid
* Installment balance
* Order amount paid
* Order remaining balance
* Payment status

When all installments are paid, mark the pautang as fully paid.

Prevent reuse of an already approved GCash reference number.

Keep payment logic simple and transactional where needed.

---

# SESSION 6 — Points System

Continue the Bigasan application.

For this session, implement the Points system.

No Laravel tests.
No Vue tests.

Customers earn points from:

* Completed orders
* On-time installment payments

Create a Points Ledger.

Do not rely only on a single manually edited points field.

Each points transaction should contain:

* Customer
* Type
* Points added/deducted
* Related order when applicable
* Description
* Date

Types:

* Order Reward
* On-Time Payment Bonus
* Redemption
* Admin Adjustment

Customer can see:

* Current points
* Peso equivalent
* Points history

Admin can:

* View customer points
* View points history
* Add/remove points through an adjustment with a reason

Do not reward points for:

* Cancelled orders
* Rejected payments
* Late payments when the reward is specifically for on-time payment

Make the points values configurable later through System Settings.

Keep the implementation simple.

---

# SESSION 7 — Points Redemption

Continue the Bigasan application.

Implement only Points Redemption in this session.

No Laravel tests.
No Vue tests.

Allow customers to use points during checkout.

Show:

* Available points
* Peso equivalent
* Points to use
* Discount
* Final amount

Rules:

* Cannot use more points than available.
* Final amount cannot become negative.
* Record all redemption in the Points Ledger.
* Do not delete ledger transactions.

If the customer has enough points to cover the full price of one sack, allow:

* Pay Fully Using Points

If an order using points is cancelled and the points need to be returned, create a new points ledger transaction returning the points.

Do not modify or delete the original redemption history.

---

# SESSION 8 — Delivery

Continue the Bigasan application.

For this session, implement Delivery.

No Laravel tests.
No Vue tests.

Initial business rule:

* Free Delivery = Daang Hari only

Do not add maps or GPS.

Use a simple delivery area system.

Delivery statuses:

* Pending
* Scheduled
* Preparing
* Out for Delivery
* Delivered
* Failed
* Cancelled

Store:

* Order
* Customer
* Address
* Delivery area
* Delivery date
* Delivery person when applicable
* Status
* Notes
* Delivered date

Admin can update delivery details/status.

Customer can see current delivery status.

Keep the implementation simple.

---

# SESSION 9 — Notifications

Continue the Bigasan application.

Implement simple in-app notifications.

No Laravel tests.
No Vue tests.

Notify customers for:

* Order confirmed
* Preparing
* Out for Delivery
* Delivered
* Payment submitted
* Payment approved
* Payment rejected
* Upcoming installment due date
* Overdue installment
* Points earned
* Points redeemed

Use Laravel's existing notification capabilities where appropriate.

Keep a notification history.

Do not add SMS, email providers, Firebase, or third-party notification services yet.

Keep it simple.

---

# SESSION 10 — Admin Dashboard

Continue the Bigasan application.

Implement the Admin Dashboard.

No Laravel tests.
No Vue tests.

Dashboard cards:

* Today's Orders
* Today's Sales
* Today's Collections
* Total Customers
* Active Pautang
* Outstanding Balance
* Overdue Balance
* Pending Payment Verifications
* Available Rice Stock
* Low Stock Products
* Points Issued
* Points Redeemed

Add simple useful charts for:

* Daily/monthly sales
* Cash vs Pautang
* Collections
* Outstanding balances
* Best-selling rice
* On-time vs late payments

Use real database data.

Do not overcomplicate charts or analytics.

---

# SESSION 11 — Reports

Continue the Bigasan application.

Implement basic Reports.

No Laravel tests.
No Vue tests.

Reports:

* Sales
* Orders
* Pautang
* Collections
* Outstanding Balances
* Overdue Customers
* Customer Payment History
* Inventory
* Inventory Movements
* Points Earned
* Points Redeemed
* Profit

Add Accounts Receivable Aging:

* Current
* 1-7 days overdue
* 8-15 days overdue
* 16-30 days overdue
* 31+ days overdue

Useful filters:

* Date range
* Customer
* Payment type
* Payment status
* Order status

Keep reports straightforward using Laravel queries.

Do not build a complicated reporting engine.

---

# SESSION 12 — System Settings

Continue the Bigasan application.

Implement System Settings.

No Laravel tests.
No Vue tests.

## General

* Business name
* Logo
* Contact number
* Address

## GCash

* Account name
* Number
* QR code

## Pautang

* Enable/disable
* Number of installments
* Payment term
* Maximum active pautang
* Maximum sacks
* Grace period

Default:

* 2 installments
* 30 days
* Maximum 1 active pautang
* Maximum 1 sack

## Points

* Enable/disable
* Points per completed order
* On-time payment bonus
* Point-to-peso conversion
* Minimum redemption
* Maximum points usable

## Delivery

* Free delivery areas

Initial:

* Daang Hari

## Inventory

* Low-stock threshold

Connect existing Bigasan business rules to these settings where appropriate.

Keep the settings implementation simple.

Do not create a complicated dynamic configuration framework.

---

# SESSION 13 — Logs

Continue the Bigasan application.

Implement Logs.

No Laravel tests.
No Vue tests.

Create Activity Logs for important actions:

* Order created
* Order cancelled
* Payment approved
* Payment rejected
* Points adjustment
* Customer suspended/reactivated
* Inventory adjustment
* Settings changed

Store:

* User
* Module
* Action
* Related record
* Description
* Date

Keep Notification Logs using the existing notification implementation.

For technical/system errors, continue using Laravel's normal logging.

Do not create a replacement logging framework.

---

# SESSION 14 — Customer Dashboard

Continue the Bigasan application.

Implement the Customer Dashboard.

No Laravel tests.
No Vue tests.

Show:

* Current points
* Peso equivalent
* Active order
* Active pautang
* Amount paid
* Remaining balance
* Next due date
* Payment status
* Delivery status
* Recent orders
* Recent payments
* Recent points transactions

Actions:

* Order Rice
* Pay Installment
* View Order
* View Points History

If the customer has unpaid pautang, clearly show that they must complete it before creating another pautang order.

Keep the dashboard simple and mobile-friendly.

---

# SESSION 15 — Admin Customer Details

Continue the Bigasan application.

Improve the Admin Customer Details page.

No Laravel tests.
No Vue tests.

Show:

* Customer information
* Account status
* Points
* Peso equivalent
* Total orders
* Active pautang
* Completed pautang
* Outstanding balance
* On-time payments
* Late payments
* Order history
* Payment history
* Points history

Admin actions:

* Edit customer
* Adjust points
* Suspend
* Reactivate

Keep related information on one clean page.

Do not overdesign it.

---

# SESSION 16 — Final Integration and Bug Check

Review the entire Bigasan application implemented in previous sessions.

Do not add new major features.

Do not create Laravel tests.
Do not create Vue tests.

Check that all modules work together correctly.

Verify these business rules:

* Maximum 1 active pautang by default.
* Maximum 1 sack for pautang.
* Previous pautang must be fully paid before another pautang can be created.
* Default pautang is 2 gives within 1 month.
* Installments default to 50/50.
* GCash payments require reference number and screenshot.
* Customer payment submissions require Admin approval.
* Only approved payments affect balances.
* Duplicate approved GCash references are prevented.
* Completed orders earn points.
* On-time payments earn bonus points.
* Late payments do not receive the on-time bonus.
* Points cannot exceed available balance when redeemed.
* Full sack can be purchased using points when sufficient.
* Inventory cannot become negative.
* Orders cannot exceed available stock.
* Cancelled orders restore inventory where appropriate.
* Suspended users cannot order.
* Daang Hari receives free delivery.
* Dashboard values are correct.
* Reports use correct values.
* Customer and Admin dashboards use real data.
* Laravel authorization and validation work.
* Vue/TypeScript has no obvious type errors.
* Browser console has no obvious errors.

Also review the code itself.

Simplify any unnecessarily complicated implementation.

Remove duplicated logic where safe.

Do not refactor working code just for style.

Keep the final code human-readable, straightforward, and easy to maintain.
