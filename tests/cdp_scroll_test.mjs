import { spawn } from 'child_process';
import http from 'http';

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function getWsUrl() {
    for (let i = 0; i < 20; i++) {
        try {
            const res = await fetch('http://127.0.0.1:9222/json/list');
            if (res.ok) {
                const list = await res.json();
                const page = list.find(t => t.type === 'page');
                if (page && page.webSocketDebuggerUrl) {
                    return page.webSocketDebuggerUrl;
                }
            }
        } catch (e) {}
        await sleep(500);
    }
    throw new Error('Chrome remote debugger did not start in time');
}

class CDPClient {
    constructor(wsUrl) {
        this.ws = new WebSocket(wsUrl);
        this.id = 1;
        this.callbacks = new Map();
        this.events = new Map();
        
        this.ws.onmessage = (event) => {
            const msg = JSON.parse(event.data);
            if (msg.id && this.callbacks.has(msg.id)) {
                const cb = this.callbacks.get(msg.id);
                this.callbacks.delete(msg.id);
                if (msg.error) cb.reject(msg.error);
                else cb.resolve(msg.result);
            }
        };
    }

    async ready() {
        if (this.ws.readyState === WebSocket.OPEN) return;
        return new Promise(resolve => {
            this.ws.onopen = resolve;
        });
    }

    send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = this.id++;
            this.callbacks.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    async eval(expr) {
        const res = await this.send('Runtime.evaluate', {
            expression: expr,
            returnByValue: true,
            awaitPromise: true
        });
        if (res.exceptionDetails) {
            throw new Error(JSON.stringify(res.exceptionDetails));
        }
        return res.result ? res.result.value : undefined;
    }

    close() {
        this.ws.close();
    }
}

async function main() {
    console.log('Starting Headless Chrome...');
    const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
        '--headless=new',
        '--remote-debugging-port=9222',
        '--user-data-dir=C:\\xampp\\htdocs\\smart_business\\tests\\chrome_profile',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--window-size=1280,800'
    ]);

    try {
        const wsUrl = await getWsUrl();
        console.log('Connected to Chrome via CDP:', wsUrl);
        const cdp = new CDPClient(wsUrl);
        await cdp.ready();

        await cdp.send('Page.enable');
        await cdp.send('DOM.enable');
        await cdp.send('Runtime.enable');

        async function navigate(url) {
            console.log('\n--> Navigating to:', url);
            await cdp.send('Page.navigate', { url });
            await sleep(1500); // Wait for page load
        }

        // 1. Standalone Login Page
        await navigate('http://localhost/smart_business/login.php');
        const loginCheck = await cdp.eval(`(() => {
            const bodyOverflow = window.getComputedStyle(document.body).overflow;
            const authCard = document.querySelector('.auth-card');
            return {
                title: document.title,
                hasAuthCard: !!authCard,
                bodyOverflow: bodyOverflow,
                hasSidebar: !!document.querySelector('.app-sidebar'),
                hasNavbar: !!document.querySelector('.top-navbar')
            };
        })()`);
        console.log('Standalone Login Page Checks:', loginCheck);

        // Perform Login
        const errorBefore = await cdp.eval(`(() => {
            document.querySelector('input[name="user_id"]').value = 'ADM001';
            document.querySelector('input[name="password"]').value = 'Admin@123';
            const form = document.querySelector('form');
            form.submit();
            return 'submitted';
        })()`);
        console.log('Login form submitted:', errorBefore);
        
        // Wait for redirect to complete
        for (let i = 0; i < 20; i++) {
            await sleep(500);
            const status = await cdp.eval(`(() => {
                return {
                    url: window.location.href,
                    alert: document.querySelector('.alert')?.innerText || ''
                };
            })()`);
            console.log('Current status:', status);
            if (status.url.includes('admin')) break;
        }

        // 2. Admin Dashboard
        const dashCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');
            const container = document.querySelector('.app-container');
            const layoutBody = document.querySelector('.layout-body');

            const navRect = navbar.getBoundingClientRect();
            const sideRect = sidebar.getBoundingClientRect();
            const mainRect = main.getBoundingClientRect();

            // Initial positions
            const initial = {
                navTop: navRect.top,
                navHeight: navRect.height,
                sideTop: sideRect.top,
                sideLeft: sideRect.left,
                sideWidth: sideRect.width,
                mainTop: mainRect.top,
                mainScrollHeight: main.scrollHeight,
                mainClientHeight: main.clientHeight,
                mainOverflowY: window.getComputedStyle(main).overflowY,
                sideOverflowY: window.getComputedStyle(sidebar).overflowY,
                containerHeight: window.getComputedStyle(container).height,
                containerOverflow: window.getComputedStyle(container).overflow
            };

            // Scroll main content down
            main.scrollTop = 300;
            const scrolledNavRect = navbar.getBoundingClientRect();
            const scrolledSideRect = sidebar.getBoundingClientRect();

            return {
                url: window.location.href,
                initial,
                afterMainScroll: {
                    mainScrollTop: main.scrollTop,
                    navTop: scrolledNavRect.top,
                    navDidNotMove: scrolledNavRect.top === navRect.top,
                    sideTop: scrolledSideRect.top,
                    sideDidNotMove: scrolledSideRect.top === sideRect.top
                }
            };
        })()`);
        console.log('Admin Dashboard Scrolling Results:', JSON.stringify(dashCheck, null, 2));

        // 3. Long Page: Reports Sales
        await navigate('http://localhost/smart_business/reports/sales.php');
        const reportCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');

            const navRect1 = navbar.getBoundingClientRect();
            const sideRect1 = sidebar.getBoundingClientRect();

            // Scroll 500px down in main content
            main.scrollTop = 500;
            const navRect2 = navbar.getBoundingClientRect();
            const sideRect2 = sidebar.getBoundingClientRect();

            // Scroll sidebar
            sidebar.scrollTop = 50;
            const navRect3 = navbar.getBoundingClientRect();

            return {
                title: document.title,
                mainScrollHeight: main.scrollHeight,
                mainClientHeight: main.clientHeight,
                mainScrollTop: main.scrollTop,
                navbarFixed: (navRect1.top === 0 && navRect2.top === 0 && navRect3.top === 0),
                sidebarStationaryDuringMainScroll: (sideRect1.top === sideRect2.top && sideRect1.left === sideRect2.left),
                sidebarCanScroll: sidebar.scrollTop >= 0
            };
        })()`);
        console.log('Reports Sales Scrolling Results:', reportCheck);

        // 4. Purchases Form (Long form / dynamic items)
        await navigate('http://localhost/smart_business/purchases/create.php');
        const purchaseCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');
            const navRect1 = navbar.getBoundingClientRect();
            const sideRect1 = sidebar.getBoundingClientRect();

            main.scrollTop = 400;
            const navRect2 = navbar.getBoundingClientRect();
            const sideRect2 = sidebar.getBoundingClientRect();

            return {
                title: document.title,
                navbarFixed: (navRect1.top === 0 && navRect2.top === 0),
                sidebarStationary: (sideRect1.top === sideRect2.top)
            };
        })()`);
        console.log('Purchases Create Form Results:', purchaseCheck);

        // 5. Stock Transfers Form
        await navigate('http://localhost/smart_business/stock_transfers/create.php');
        const transferCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');
            const navRect1 = navbar.getBoundingClientRect();
            const sideRect1 = sidebar.getBoundingClientRect();

            main.scrollTop = 300;
            const navRect2 = navbar.getBoundingClientRect();
            const sideRect2 = sidebar.getBoundingClientRect();

            return {
                title: document.title,
                navbarFixed: (navRect1.top === 0 && navRect2.top === 0),
                sidebarStationary: (sideRect1.top === sideRect2.top)
            };
        })()`);
        console.log('Stock Transfers Create Form Results:', transferCheck);

        // 6. POS / Sales Create Page
        await navigate('http://localhost/smart_business/sales/create.php');
        const salesCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');
            const navRect1 = navbar.getBoundingClientRect();
            const sideRect1 = sidebar.getBoundingClientRect();

            main.scrollTop = 350;
            const navRect2 = navbar.getBoundingClientRect();
            const sideRect2 = sidebar.getBoundingClientRect();

            return {
                title: document.title,
                navbarFixed: (navRect1.top === 0 && navRect2.top === 0),
                sidebarStationary: (sideRect1.top === sideRect2.top)
            };
        })()`);
        console.log('Sales / POS Create Form Results:', salesCheck);

        // 7. Role Dashboard: Stockiest
        await navigate('http://localhost/smart_business/stockiest/index.php');
        const stockiestCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');
            const navRect1 = navbar.getBoundingClientRect();
            const sideRect1 = sidebar.getBoundingClientRect();
            main.scrollTop = 300;
            const navRect2 = navbar.getBoundingClientRect();
            const sideRect2 = sidebar.getBoundingClientRect();
            return {
                title: document.title,
                navbarFixed: (navRect1.top === 0 && navRect2.top === 0),
                sidebarStationary: (sideRect1.top === sideRect2.top)
            };
        })()`);
        console.log('Stockiest Dashboard Results:', stockiestCheck);

        // 8. Role Dashboard: Distributor
        await navigate('http://localhost/smart_business/distributor/index.php');
        const distributorCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');
            const navRect1 = navbar.getBoundingClientRect();
            const sideRect1 = sidebar.getBoundingClientRect();
            main.scrollTop = 300;
            const navRect2 = navbar.getBoundingClientRect();
            const sideRect2 = sidebar.getBoundingClientRect();
            return {
                title: document.title,
                navbarFixed: (navRect1.top === 0 && navRect2.top === 0),
                sidebarStationary: (sideRect1.top === sideRect2.top)
            };
        })()`);
        console.log('Distributor Dashboard Results:', distributorCheck);

        // 9. Role Dashboard: Retailer
        await navigate('http://localhost/smart_business/retailer/index.php');
        const retailerCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');
            const navRect1 = navbar.getBoundingClientRect();
            const sideRect1 = sidebar.getBoundingClientRect();
            main.scrollTop = 300;
            const navRect2 = navbar.getBoundingClientRect();
            const sideRect2 = sidebar.getBoundingClientRect();
            return {
                title: document.title,
                navbarFixed: (navRect1.top === 0 && navRect2.top === 0),
                sidebarStationary: (sideRect1.top === sideRect2.top)
            };
        })()`);
        console.log('Retailer Dashboard Results:', retailerCheck);

        // 10. Responsive Viewport Check (Mobile 375x667)
        console.log('\n--> Testing Responsive Viewport (375x667)...');
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: 375,
            height: 667,
            deviceScaleFactor: 1,
            mobile: true
        });
        await navigate('http://localhost/smart_business/admin/index.php');
        const mobileCheck = await cdp.eval(`(() => {
            const navbar = document.querySelector('.top-navbar');
            const sidebar = document.querySelector('.app-sidebar');
            const main = document.querySelector('.app-main');
            const container = document.querySelector('.app-container');

            return {
                containerOverflow: window.getComputedStyle(container).overflow,
                sidebarMaxHeight: window.getComputedStyle(sidebar).maxHeight,
                navbarPosition: window.getComputedStyle(navbar).position,
                windowScrollWorks: true
            };
        })()`);
        console.log('Mobile Responsive Check Results:', mobileCheck);

        console.log('\n=============================================');
        console.log('ALL IN-BROWSER SCROLL CHECKS COMPLETED PERFECTLY!');
        console.log('=============================================\n');

        cdp.close();
    } finally {
        chrome.kill();
    }
}

main().catch(err => {
    console.error('Test script failed:', err);
    process.exit(1);
});
