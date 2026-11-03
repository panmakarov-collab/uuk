<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Accounts</title>
    <style>
        .account {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Salary Accounts</h1>
    <button onclick="openModal()">Add Account</button>
    <div id="accountList">
        <!-- Список счетов будет загружаться динамически -->
    </div>

    <!-- Модальное окно для добавления счета -->
    <div id="addAccountModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Add Salary Account</h2>
            <form id="addAccountForm">
                <label for="organization">Organization Name:</label><br>
                <input type="text" id="organization" name="organization" required><br>
                <label for="bank">Bank Name:</label><br>
                <input type="text" id="bank" name="bank" required><br>
                <label for="balance">Initial Balance:</label><br>
                <input type="number" id="balance" name="balance" required><br>
                <label for="payday">Payday (1-31):</label><br>
                <input type="number" id="payday" name="payday" min="1" max="31" required><br>
                <br>
                <input type="submit" value="Add Account">
            </form>
        </div>
    </div>

    <script>
        // Открытие и закрытие модального окна
        function openModal() {
            document.getElementById("addAccountModal").style.display = "block";
        }
        function closeModal() {
            document.getElementById("addAccountModal").style.display = "none";
        }

        // Обработка добавления счета
        document.getElementById("addAccountForm").addEventListener("submit", function(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            fetch('add_salary_account.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text()).then(data => {
                alert(data);
                closeModal();
                loadAccounts(); // Обновить список счетов
            });
        });

        // Загрузка списка счетов
        function loadAccounts() {
            fetch('get_salary_accounts.php')
                .then(response => response.json())
                .then(accounts => {
                    const accountList = document.getElementById("accountList");
                    accountList.innerHTML = "";
                    accounts.forEach(account => {
                        const div = document.createElement("div");
                        div.className = "account";
                        div.innerHTML = `
                            <h3>${account.organization}</h3>
                            <p>Bank: ${account.bank}</p>
                            <p>Balance: ${account.balance}</p>
                            <p>Payday: ${account.payday}</p>
                        `;
                        accountList.appendChild(div);
                    });
                });
        }

        // Инициализация страницы
        loadAccounts();
    </script>
</body>
</html>
